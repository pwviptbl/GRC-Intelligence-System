<?php

namespace App\Services;

use App\Models\Agente;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;
use App\Models\InstanciaCliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Alvos e resultados dos agentes externos. A varredura do agente é de perímetro:
 * fica num engajamento de infra do software, ligado aos ambientes testados, e não conta no ciclo de testes.
 */
class AgenteService
{
    public const ENGAJAMENTO = 'Varreduras Automatizadas (Agente)';

    /** Situações em que o achado está ativo e pode ser fechado quando some da varredura */
    public const ATIVOS = ['aberto', 'confirmado', 'em_tratamento'];

    public function __construct(
        protected ScanImportService $importador,
        protected FindingDeduplicationService $dedup,
    ) {}

    /**
     * Ambientes de software ativo com pelo menos uma porta aberta reconhecida pela varredura de portas.
     */
    public function alvosQuery(): Builder
    {
        return InstanciaCliente::query()
            ->whereHas('software', fn ($q) => $q->where('ativo', true))
            ->whereHas('portasAbertas');
    }

    public function alvos(): array
    {
        return $this->alvosQuery()
            ->with(['cliente:id,nome', 'software:id,nome', 'portasAbertas'])
            ->orderBy('id')
            ->get()
            ->map(fn (InstanciaCliente $i) => $this->descreverAlvo($i))
            ->filter(fn (array $alvo) => $alvo['host'])
            ->values()
            ->all();
    }

    public function descreverAlvo(InstanciaCliente $i): array
    {
        $host = $i->hostname;
        $portas = $i->portasAbertas->map(fn ($p) => ['porta' => (int) $p->porta, 'servico' => $p->servico])->values()->all();

        $urls = collect($portas)
            ->map(function (array $p) use ($host) {
                $https = in_array($p['porta'], [443, 8443], true) || str_contains((string) $p['servico'], 'https');
                $http = $https || in_array($p['porta'], [80, 8000, 8080, 8081, 8888], true) || str_contains((string) $p['servico'], 'http');
                if (! $http || ! $host) {
                    return null;
                }
                $esquema = $https ? 'https' : 'http';
                $padrao = ($https && $p['porta'] === 443) || (! $https && $p['porta'] === 80);

                return $esquema.'://'.$host.($padrao ? '' : ':'.$p['porta']);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $i->id,
            'organizacao' => $i->cliente?->nome,
            'software' => $i->software?->nome,
            'ambiente' => $i->nome_ambiente,
            'host' => $host,
            'ip' => $i->ip_varredura ?: $i->endereco_ip,
            'cdn' => $i->cdn_provedor,
            'exposicao' => $i->status_exposicao,
            'portas' => $portas,
            'urls' => $urls,
        ];
    }

    /**
     * Importa o resultado de uma varredura recorrente: o que repete atualiza a última detecção,
     * o que é novo vira achado, o que sumiu é fechado e o informativo é ignorado.
     */
    public function importar(Agente $agente, InstanciaCliente $instancia, string $ferramenta, string $arquivo, string $conteudo): array
    {
        $parser = $this->importador->parser($ferramenta);
        $itens = $parser->parse($conteudo);

        $informativos = $itens->where('severidade', 'informativo')->count();
        $itens = $itens->reject(fn (array $item) => $item['severidade'] === 'informativo');

        return DB::transaction(function () use ($agente, $instancia, $parser, $itens, $informativos, $arquivo, $conteudo) {
            $teste = $this->testeDoAmbiente($instancia, $parser->getName(), $agente);
            $softwareId = (int) $instancia->software_id;
            $hoje = now()->toDateString();

            $existentes = Finding::where('test_id', $teste->id)->orderBy('id')->get()->keyBy('hash_dedup');
            $vistos = [];
            $contagem = ['novos' => 0, 'repetidos' => 0, 'reabertos' => 0, 'fechados' => 0, 'informativos_ignorados' => $informativos];

            foreach ($itens as $item) {
                $hash = $this->dedup->computeHash((string) $item['titulo'], $item['endpoint'] ?? null, $item['cwe_id'] ?? null, $softwareId);
                if (isset($vistos[$hash])) {
                    continue;
                }
                $vistos[$hash] = true;

                $finding = $existentes->get($hash);
                if (! $finding) {
                    Finding::create($item + [
                        'test_id' => $teste->id,
                        'hash_dedup' => $hash,
                        'ativo_afetado' => $instancia->hostname,
                        'ultima_deteccao_em' => $hoje,
                    ]);
                    $contagem['novos']++;

                    continue;
                }

                $dados = ['ultima_deteccao_em' => $hoje];
                if ($finding->status === 'fechado') {
                    $dados += [
                        'status' => 'aberto',
                        'corrigido_em' => null,
                        'is_regression' => true,
                        'descricao' => $finding->descricao."\n\nReaberto pelo agente {$agente->nome} em ".now()->format('d/m/Y H:i').': detectado de novo.',
                    ];
                    $contagem['reabertos']++;
                } else {
                    $contagem['repetidos']++;
                }
                $finding->update($dados);
            }

            $existentes
                ->filter(fn (Finding $f) => ! isset($vistos[$f->hash_dedup]) && in_array($f->status, self::ATIVOS, true))
                ->each(function (Finding $f) use ($agente, &$contagem) {
                    $f->update([
                        'status' => 'fechado',
                        'corrigido_em' => now()->toDateString(),
                        'descricao' => $f->descricao."\n\nFechado automaticamente pelo agente {$agente->nome} em ".now()->format('d/m/Y H:i').': não foi detectado na varredura.',
                    ]);
                    $contagem['fechados']++;
                });

            $caminho = 'scans/'.$teste->id.'/'.now()->format('YmdHis').'_'.Str::slug(pathinfo($arquivo, PATHINFO_FILENAME) ?: 'resultado').'.'.(pathinfo($arquivo, PATHINFO_EXTENSION) ?: 'txt');
            Storage::disk('local')->put($caminho, $conteudo);

            $teste->update([
                'arquivo_scan' => $caminho,
                'formato_scan' => $parser->getKey(),
                'status' => 'concluido',
                'data_fim' => now()->toDateString(),
            ]);

            return ['teste_id' => $teste->id, 'ferramenta' => $parser->getName()] + $contagem;
        });
    }

    /**
     * Um teste por ambiente e ferramenta, no engajamento de infra do software, ligado ao ambiente.
     */
    protected function testeDoAmbiente(InstanciaCliente $instancia, string $ferramenta, Agente $agente): EngagementTest
    {
        $engajamento = Engagement::firstOrCreate(
            ['software_id' => $instancia->software_id, 'tipo' => 'infra', 'nome' => self::ENGAJAMENTO],
            [
                'descricao' => 'Varreduras de perímetro enviadas pelos agentes externos. Não contam no ciclo de testes do software.',
                'status' => 'ativo',
                'lead' => 'Agente / Sistema',
                'data_inicio' => now(),
                'auto_propagar_branch' => false,
            ]
        );
        $engajamento->instancias()->syncWithoutDetaching([$instancia->id]);

        $nomeFerramenta = explode(' ', $ferramenta)[0];

        return EngagementTest::firstOrCreate(
            [
                'engagement_id' => $engajamento->id,
                'titulo' => "Varredura {$nomeFerramenta} — ".($instancia->hostname ?: 'Ambiente #'.$instancia->id),
                'tipo_teste' => 'infra',
            ],
            [
                'ferramenta' => $nomeFerramenta.' (agente '.$agente->nome.')',
                'ambiente' => $instancia->nome_ambiente ?: 'Produção',
                'status' => 'em_andamento',
                'data_inicio' => now(),
            ]
        );
    }
}
