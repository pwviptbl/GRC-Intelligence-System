<?php

namespace App\Services;

use App\Models\Controle;
use App\Models\ControleImplantacao;
use App\Models\InstanciaCliente;
use App\Models\Software;
use Illuminate\Support\Collection;

/**
 * Matriz "quem tem / quem não tem" dos controles implantados.
 */
class ControleCoberturaService
{
    public const SECOES = [
        'software' => 'Softwares',
        'repositorio' => 'Repositórios',
        'ambiente' => 'Ambientes',
    ];

    /** Situações que contam como "não tem" quando o controle é exigido */
    public const FALTA = ['nao_implantado', 'em_implantacao'];

    /**
     * Alvos de um tipo de controle, na ordem da matriz.
     * software: um por software ativo; ambiente: um por ambiente dos softwares ativos;
     * repositorio: o repositório padrão de cada software e, abaixo dele, os repositórios próprios das organizações.
     * Com uma organização, ficam só os softwares que ela usa, os ambientes dela e, no repositório, o padrão
     * apenas quando algum ambiente dela não tem repositório próprio.
     *
     * @return Collection<int, array{chave: string, software_id: ?int, instancia_cliente_id: ?int, grupo_id: int, grupo: string, nome: string, detalhe: ?string, tier: ?int}>
     */
    public function alvos(string $aplicaEm, ?int $softwareId = null, ?int $clienteId = null): Collection
    {
        $doCliente = $clienteId
            ? InstanciaCliente::query()->where('cliente_id', $clienteId)->get(['id', 'software_id', 'git_custom_url'])
            : null;

        $softwares = Software::query()
            ->where('ativo', true)
            ->when($softwareId, fn ($q) => $q->whereKey($softwareId))
            ->when($doCliente, fn ($q) => $q->whereIn('id', $doCliente->pluck('software_id')))
            ->orderBy('nome')
            ->get();

        $doSoftware = fn (Software $software) => [
            'chave' => 's:'.$software->id,
            'software_id' => $software->id,
            'instancia_cliente_id' => null,
            'grupo_id' => $software->id,
            'grupo' => $software->nome,
            'nome' => $software->nome,
            'detalhe' => $aplicaEm === 'repositorio' ? ($software->git_url ?: 'Repositório padrão não informado') : null,
            'tier' => $software->tier_sugerido,
        ];

        if ($aplicaEm === 'software') {
            return $softwares->map($doSoftware)->values();
        }

        $instancias = InstanciaCliente::query()
            ->with('cliente:id,nome')
            ->whereIn('software_id', $softwares->pluck('id'))
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when($aplicaEm === 'repositorio', fn ($q) => $q->whereNotNull('git_custom_url')->where('git_custom_url', '<>', ''))
            ->get()
            ->groupBy('software_id');

        return $softwares->flatMap(function (Software $software) use ($aplicaEm, $instancias, $doSoftware, $doCliente) {
            $ambientes = $instancias->get($software->id, collect())
                ->sortBy(fn (InstanciaCliente $i) => mb_strtolower(($i->cliente?->nome ?? '').' '.($i->nome_ambiente ?? '')))
                ->map(fn (InstanciaCliente $i) => [
                    'chave' => 'i:'.$i->id,
                    'software_id' => null,
                    'instancia_cliente_id' => $i->id,
                    'grupo_id' => $software->id,
                    'grupo' => $software->nome,
                    'nome' => ($i->cliente?->nome ?? 'Sem organização').($i->nome_ambiente ? ' — '.$i->nome_ambiente : ''),
                    'detalhe' => $aplicaEm === 'repositorio' ? $i->git_custom_url : $i->url_principal,
                    'tier' => $software->tier_sugerido,
                ]);

            if ($aplicaEm !== 'repositorio') {
                return $ambientes;
            }

            $usaPadrao = ! $doCliente || $doCliente->where('software_id', $software->id)->contains(fn ($i) => blank($i->git_custom_url));

            return collect($usaPadrao ? [$doSoftware($software)] : [])->concat($ambientes);
        })->values();
    }

    /**
     * Monta a matriz: uma seção por tipo de alvo, uma coluna por controle e o resumo de cobertura de cada um.
     * O resumo considera os filtros de software e organização, mas não os de situação e busca.
     *
     * @param  Collection<int, Controle>  $controles
     * @param  array{controle_id?: int|string|null, status?: string|null, software_id?: int|string|null, cliente_id?: int|string|null, busca?: string|null}  $filtros
     */
    public function montar(Collection $controles, array $filtros = []): array
    {
        $foco = ! empty($filtros['controle_id']) ? (int) $filtros['controle_id'] : null;
        $status = $filtros['status'] ?? null;
        $busca = trim((string) ($filtros['busca'] ?? ''));
        $softwareId = ! empty($filtros['software_id']) ? (int) $filtros['software_id'] : null;
        $clienteId = ! empty($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : null;

        $implantacoes = ControleImplantacao::query()
            ->with('autor:id,name')
            ->whereIn('controle_id', $controles->pluck('id'))
            ->get()
            ->groupBy('controle_id')
            ->map(fn (Collection $itens) => $itens->keyBy('chave_alvo'));

        $secoes = [];
        $resumos = [];

        foreach (self::SECOES as $tipo => $titulo) {
            $doTipo = $controles->where('aplica_em', $tipo)->values();

            if ($doTipo->isEmpty()) {
                continue;
            }

            $linhas = $this->alvos($tipo, $softwareId, $clienteId)->map(function (array $alvo) use ($doTipo, $implantacoes) {
                $alvo['celulas'] = [];

                foreach ($doTipo as $controle) {
                    $alvo['celulas'][$controle->id] = $this->celula($controle, $alvo, $implantacoes->get($controle->id)?->get($alvo['chave']));
                }

                return $alvo;
            });

            foreach ($doTipo as $controle) {
                $resumos[$controle->id] = $this->resumir($linhas->pluck('celulas.'.$controle->id));
            }

            $colunas = $foco ? $doTipo->where('id', $foco)->values() : $doTipo;

            if ($colunas->isEmpty()) {
                continue;
            }

            $linhas = $linhas->filter(function (array $alvo) use ($colunas, $status, $busca) {
                if ($busca !== '' && mb_stripos($alvo['grupo'].' '.$alvo['nome'].' '.$alvo['detalhe'], $busca) === false) {
                    return false;
                }

                return ! $status || $colunas->contains(fn (Controle $c) => $this->combina($alvo['celulas'][$c->id], $status));
            })->values();

            $secoes[] = ['tipo' => $tipo, 'titulo' => $titulo, 'colunas' => $colunas, 'linhas' => $linhas];
        }

        return ['secoes' => $secoes, 'resumos' => $resumos];
    }

    /**
     * Registra a situação do controle em um alvo (cria ou atualiza a única linha do par controle × alvo).
     */
    public function salvar(Controle $controle, array $alvo, array $dados, ?int $userId): ControleImplantacao
    {
        $chave = $alvo['instancia_cliente_id']
            ? ['instancia_cliente_id' => $alvo['instancia_cliente_id']]
            : ['software_id' => $alvo['software_id']];

        return ControleImplantacao::updateOrCreate(
            ['controle_id' => $controle->id] + $chave,
            $dados + ['atualizado_por' => $userId]
        );
    }

    protected function celula(Controle $controle, array $alvo, ?ControleImplantacao $implantacao): array
    {
        $exigido = $controle->exigidoPara($alvo['tier']);
        $status = $implantacao?->status ?? 'nao_implantado';

        return [
            'status' => $status,
            'registrado' => $implantacao !== null,
            'exigido' => $exigido,
            'falta' => $exigido && in_array($status, self::FALTA, true),
            'implantacao' => $implantacao,
        ];
    }

    protected function combina(array $celula, string $status): bool
    {
        return $status === 'sem'
            ? $celula['falta']
            : $celula['registrado'] && $celula['status'] === $status;
    }

    /**
     * Cobertura de um controle: implantados sobre os alvos em que ele é exigido, sem contar os "não se aplica".
     */
    protected function resumir(Collection $celulas): array
    {
        $escopo = $celulas->where('exigido', true);
        $implantados = $escopo->where('status', 'implantado')->count();
        $naoAplica = $escopo->where('status', 'nao_aplica')->count();
        $base = $escopo->count() - $naoAplica;

        return [
            'alvos' => $celulas->count(),
            'escopo' => $escopo->count(),
            'implantados' => $implantados,
            'em_implantacao' => $escopo->where('status', 'em_implantacao')->count(),
            'nao_aplica' => $naoAplica,
            'faltam' => $base - $implantados,
            'percentual' => $base > 0 ? (int) round(100 * $implantados / $base) : null,
        ];
    }
}
