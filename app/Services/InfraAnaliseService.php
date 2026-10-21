<?php

namespace App\Services;

use App\Models\InfraComponente;
use App\Models\InfraFluxo;
use App\Models\InfraMapa;
use Illuminate\Support\Collection;

/**
 * Revisão de segmentação de um mapa: aponta as comunicações liberadas que não deveriam existir
 * ou que estão mais abertas que o necessário, para propor o isolamento.
 */
class InfraAnaliseService
{
    public const NIVEIS = [
        'critico' => ['label' => 'Crítico', 'cor' => 'var(--red)'],
        'alto' => ['label' => 'Alto', 'cor' => '#f97316'],
        'medio' => ['label' => 'Médio', 'cor' => 'var(--yellow)'],
        'info' => ['label' => 'Informativo', 'cor' => 'var(--cyan)'],
    ];

    /**
     * @return list<array{nivel: string, titulo: string, detalhe: string, fluxo_id: ?int, componente_id: ?int}>
     */
    public function analisar(InfraMapa $mapa): array
    {
        $componentes = $mapa->componentes()->get()->keyBy('id');
        $fluxos = $mapa->fluxos()->get();
        $liberados = $fluxos->where('liberado', true);
        $achados = [];

        // 1. Serviço sensível recebendo direto da internet (ou um grupo que o contém)
        foreach ($liberados as $fluxo) {
            $origem = $componentes->get($fluxo->origem_id);
            if ($origem?->tipo !== 'internet') {
                continue;
            }

            foreach ($this->comDescendentes($fluxo->destino_id, $componentes) as $alvo) {
                if (in_array($alvo->tipo, InfraComponente::SENSIVEIS, true)) {
                    $achados[] = $this->achado('critico',
                        InfraComponente::TIPOS[$alvo->tipo]." acessível pela internet: {$alvo->nome}",
                        "Regra liberada da internet para {$componentes->get($fluxo->destino_id)?->nome} ({$this->descreverPortas($fluxo)}). Restrinja a origem (VPN, IPs da empresa) ou remova a regra.",
                        $fluxo->id, $alvo->id);
                }
            }
        }

        // 2. Comunicação marcada como desnecessária que continua liberada
        foreach ($liberados->where('classificacao', 'desnecessario') as $fluxo) {
            $achados[] = $this->achado('alto',
                'Comunicação desnecessária liberada: '.$this->descreverFluxo($fluxo, $componentes),
                'Marcada como desnecessária, mas a rede ainda permite. Proposta: bloquear no NSG / Security List / firewall.',
                $fluxo->id);
        }

        // 3. Regra liberando todas as portas
        foreach ($liberados->filter(fn (InfraFluxo $f) => $f->todas_portas) as $fluxo) {
            $achados[] = $this->achado('medio',
                'Regra com todas as portas: '.$this->descreverFluxo($fluxo, $componentes),
                'Restrinja às portas que o serviço realmente usa.',
                $fluxo->id);
        }

        // 4. Comunicações ainda não classificadas
        $verificar = $liberados->where('classificacao', 'verificar');
        if ($verificar->isNotEmpty()) {
            $achados[] = $this->achado('info',
                "{$verificar->count()} comunicação(ões) liberada(s) a verificar",
                'Confirme com o time de infra se cada uma é necessária; as que não forem entram na proposta de bloqueio.');
        }

        $ordem = array_flip(array_keys(self::NIVEIS));
        usort($achados, fn ($a, $b) => $ordem[$a['nivel']] <=> $ordem[$b['nivel']]);

        return $achados;
    }

    /**
     * Resumo da proposta: o que bloquear para chegar no "como deveria ser".
     */
    public function resumo(InfraMapa $mapa): array
    {
        $fluxos = $mapa->fluxos()->get();
        $liberados = $fluxos->where('liberado', true);

        return [
            'componentes' => $mapa->componentes()->count(),
            'fluxos' => $fluxos->count(),
            'necessarios' => $liberados->where('classificacao', 'necessario')->count(),
            'a_bloquear' => $liberados->where('classificacao', 'desnecessario')->count(),
            'a_verificar' => $liberados->where('classificacao', 'verificar')->count(),
            'bloqueados' => $fluxos->where('liberado', false)->count(),
        ];
    }

    /**
     * O componente e tudo que está dentro dele.
     *
     * @param  Collection<int, InfraComponente>  $componentes
     * @return list<InfraComponente>
     */
    protected function comDescendentes(int $id, Collection $componentes): array
    {
        $resultado = [];
        $fila = [$id];
        $vistos = [];

        while ($fila) {
            $atual = array_shift($fila);
            if (isset($vistos[$atual]) || ! $componentes->has($atual)) {
                continue;
            }
            $vistos[$atual] = true;
            $resultado[] = $componentes->get($atual);
            foreach ($componentes->where('parent_id', $atual) as $filho) {
                $fila[] = $filho->id;
            }
        }

        return $resultado;
    }

    protected function descreverFluxo(InfraFluxo $fluxo, Collection $componentes): string
    {
        return ($componentes->get($fluxo->origem_id)?->nome ?? '?').' → '.($componentes->get($fluxo->destino_id)?->nome ?? '?').' ('.$this->descreverPortas($fluxo).')';
    }

    protected function descreverPortas(InfraFluxo $fluxo): string
    {
        return $fluxo->todas_portas ? 'todas as portas' : strtoupper($fluxo->protocolo).' '.$fluxo->portas;
    }

    protected function achado(string $nivel, string $titulo, string $detalhe, ?int $fluxoId = null, ?int $componenteId = null): array
    {
        return ['nivel' => $nivel, 'titulo' => $titulo, 'detalhe' => $detalhe, 'fluxo_id' => $fluxoId, 'componente_id' => $componenteId];
    }
}
