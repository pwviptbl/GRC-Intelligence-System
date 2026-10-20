<?php

namespace App\Services;

use App\Models\Agente;
use App\Models\AgenteExecucao;
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
     * Cria um pedido de varredura. Sem ambientes: todos os alvos, ou, no agendamento com rodízio,
     * os N alvos varridos há mais tempo.
     *
     * @param  list<int>|null  $instanciaIds
     */
    public function solicitar(Agente $agente, ?array $instanciaIds = null, string $origem = 'manual', ?int $userId = null): AgenteExecucao
    {
        $query = $this->alvosQuery()->orderByRaw('agente_varrido_em asc nulls first')->orderBy('id');

        if ($instanciaIds !== null) {
            $query->whereIn('id', $instanciaIds);
        } elseif ($origem === 'agendada' && $agente->alvos_por_execucao) {
            $query->limit($agente->alvos_por_execucao);
        }

        return $agente->execucoes()->create([
            'origem' => $origem,
            'status' => 'pendente',
            'alvos' => $query->pluck('id')->all(),
            'solicitado_por' => $userId,
        ]);
    }

    /**
     * Cria as execuções agendadas que já venceram hoje (chamado a cada minuto pelo scheduler).
     * Um agente com execução em aberto não ganha outra: a de hoje sai quando a anterior terminar.
     */
    public function agendarDevidas(): int
    {
        $criadas = 0;

        foreach (Agente::where('ativo', true)->where('agendamento_ativo', true)->get() as $agente) {
            $horario = $agente->agendamentoDeHoje();
            if (! $horario || now()->lt($horario)) {
                continue;
            }

            $jaCriada = $agente->execucoes()->where('origem', 'agendada')->where('created_at', '>=', $horario)->exists();
            $emAberto = $agente->execucoes()->whereIn('status', AgenteExecucao::ABERTAS)->exists();
            if ($jaCriada || $emAberto) {
                continue;
            }

            $this->solicitar($agente, origem: 'agendada');
            $criadas++;
        }

        return $criadas;
    }

    /**
     * Próxima execução do agente: a que ele já tinha começado (reinício do agente) ou a mais antiga na fila.
     * Execução sem notícia há muito tempo é dada como falha.
     */
    public function proximaTarefa(Agente $agente): ?AgenteExecucao
    {
        $agente->execucoes()
            ->where('status', 'em_andamento')
            ->where('updated_at', '<', now()->subHours(AgenteExecucao::HORAS_SEM_RETORNO))
            ->update(['status' => 'falha', 'finalizado_em' => now(), 'mensagem' => 'Sem retorno do agente por '.AgenteExecucao::HORAS_SEM_RETORNO.'h.']);

        $execucao = $agente->execucoes()->where('status', 'em_andamento')->reorder('id')->first()
            ?? $agente->execucoes()->where('status', 'pendente')->reorder('id')->first();

        if ($execucao && $execucao->status === 'pendente') {
            $execucao->update(['status' => 'em_andamento', 'iniciado_em' => now()]);
        }

        return $execucao;
    }

    /**
     * Descrição dos alvos de uma execução, só dos que continuam na lista (porta aberta, software ativo).
     */
    public function alvosDaExecucao(AgenteExecucao $execucao): array
    {
        return $this->alvosQuery()
            ->whereIn('id', $execucao->alvos ?? [])
            ->with(['cliente:id,nome', 'software:id,nome', 'portasAbertas'])
            ->orderByRaw('agente_varrido_em asc nulls first')
            ->orderBy('id')
            ->get()
            ->map(fn (InstanciaCliente $i) => $this->descreverAlvo($i))
            ->filter(fn (array $alvo) => $alvo['host'])
            ->values()
            ->all();
    }

    public function registrarProgresso(AgenteExecucao $execucao, array $resultado): void
    {
        $resumo = $execucao->resumo ?? [];
        foreach (['novos', 'repetidos', 'reabertos', 'fechados', 'informativos_ignorados'] as $campo) {
            $resumo[$campo] = ($resumo[$campo] ?? 0) + ($resultado[$campo] ?? 0);
        }

        $execucao->update(['resumo' => $resumo, 'alvos_processados' => $execucao->alvos_processados + 1]);
    }

    public function concluir(AgenteExecucao $execucao, bool $sucesso, ?string $mensagem = null): void
    {
        if (! $execucao->aberta) {
            return;
        }

        $execucao->update([
            'status' => $sucesso ? 'concluida' : 'falha',
            'finalizado_em' => now(),
            'mensagem' => $mensagem ? mb_substr($mensagem, 0, 4000) : null,
        ]);
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

            $instancia->forceFill(['agente_varrido_em' => now()])->saveQuietly();

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
