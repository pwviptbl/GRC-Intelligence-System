<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Software;
use App\Models\Engagement;
use App\Models\EngagementTest;
use App\Models\Finding;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Converte registros históricos da tabela 'riscos' para a hierarquia DefectDojo:
     * Software -> Engagement -> EngagementTest -> Finding.
     */
    public function up(): void
    {
        $riscos = DB::table('riscos')->get();
        if ($riscos->isEmpty()) {
            return;
        }

        // Pega software padrão para riscos sem software_id definido
        $defaultSoftwareId = DB::table('software')->where('ativo', true)->orderBy('id')->value('id')
            ?? DB::table('software')->orderBy('id')->value('id');

        // Mapeamentos de criticidade
        $severidadeMap = [
            'Critico'     => 'critico',
            'critico'     => 'critico',
            'Alto'        => 'alto',
            'alto'        => 'alto',
            'Medio'       => 'medio',
            'medio'       => 'medio',
            'Baixo'       => 'baixo',
            'baixo'       => 'baixo',
            'Informativo' => 'informativo',
            'informativo' => 'informativo',
        ];

        // Mapeamentos de status
        $statusMap = [
            'aberto'        => 'aberto',
            'em_andamento'  => 'em_tratamento',
            'em_tratamento' => 'em_tratamento',
            'fechado'       => 'fechado',
            'mitigado'      => 'fechado',
            'risco_aceito'  => 'risco_aceito',
            'cancelado'     => 'fechado',
        ];

        // Cache de testes criados por software
        $testsBySoftware = [];

        foreach ($riscos as $r) {
            $softwareId = $r->software_id ?: $defaultSoftwareId;
            if (!$softwareId) {
                continue;
            }

            if (!isset($testsBySoftware[$softwareId])) {
                // Localiza ou cria o engajamento de migração legado
                $engagement = Engagement::firstOrCreate(
                    [
                        'software_id' => $softwareId,
                        'nome'        => 'Inventário de Riscos e Vulnerabilidades (Legado)',
                    ],
                    [
                        'tipo'        => 'auditoria',
                        'descricao'   => 'Engajamento criado na migração automática a partir do registro histórico de riscos e vulnerabilidades.',
                        'status'      => 'ativo',
                        'lead'        => 'Migração do Sistema',
                        'data_inicio' => $r->created_at ? substr($r->created_at, 0, 10) : now()->toDateString(),
                    ]
                );

                // Localiza ou cria o teste de migração legado
                $test = EngagementTest::firstOrCreate(
                    [
                        'engagement_id' => $engagement->id,
                        'titulo'        => 'Mapeamento Histórico de Riscos',
                    ],
                    [
                        'tipo_teste'  => 'manual',
                        'ferramenta'  => 'Manual',
                        'ambiente'    => 'producao',
                        'status'      => 'concluido',
                        'data_inicio' => $r->created_at ? substr($r->created_at, 0, 10) : now()->toDateString(),
                        'notas'       => 'Test container para achados migrados do antigo registro de riscos.',
                    ]
                );

                $testsBySoftware[$softwareId] = $test;
            }

            $test = $testsBySoftware[$softwareId];

            $severidade = $severidadeMap[$r->criticidade ?? ''] ?? 'medio';
            $status     = $statusMap[$r->status ?? ''] ?? 'aberto';

            $hashDedup = hash('sha256', implode('|', [
                strtolower(trim($r->titulo ?? '')),
                '', // endpoint
                '', // cwe_id
                (string)$softwareId
            ]));

            // Verifica se o finding já existe por hash no mesmo teste
            $existing = DB::table('findings')->where('test_id', $test->id)->where('hash_dedup', $hashDedup)->first();
            if ($existing) {
                continue;
            }

            $findingId = DB::table('findings')->insertGetId([
                'test_id'               => $test->id,
                'titulo'                => $r->titulo,
                'descricao'             => $r->descricao ?? '',
                'severidade'            => $severidade,
                'cvss_score'            => $r->cvss_score,
                'cve_id'                => $r->cve_id,
                'remediacao_sugerida'   => $r->plano_acao,
                'status'                => $status,
                'ativo_afetado'         => $r->ativo_afetado,
                'responsavel'           => $r->responsavel,
                'sla_dias'              => $r->sla_dias ?? 30,
                'data_limite_correcao'  => $r->data_limite_correcao,
                'hash_dedup'            => $hashDedup,
                'falso_positivo'        => false,
                'aceito_risco'          => ($status === 'risco_aceito'),
                'is_regression'         => false,
                'detectado_em'          => $r->created_at ? substr($r->created_at, 0, 10) : now()->toDateString(),
                'corrigido_em'          => ($status === 'fechado' && $r->updated_at) ? substr($r->updated_at, 0, 10) : null,
                'created_at'            => $r->created_at ?? now(),
                'updated_at'            => $r->updated_at ?? now(),
            ]);

            // Se o risco tinha vínculo com controle de atividade, vincula na tabela pivot finding_atividades
            if (!empty($r->atividade_id)) {
                $atividadeExists = DB::table('atividades')->where('id', $r->atividade_id)->exists();
                if ($atividadeExists) {
                    DB::table('finding_atividades')->insertOrIgnore([
                        'finding_id'   => $findingId,
                        'atividade_id' => $r->atividade_id,
                        'notas'        => 'Migrado do vínculo de atividade do risco antigo #' . $r->id,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $engIds = DB::table('engagements')
            ->where('nome', 'Inventário de Riscos e Vulnerabilidades (Legado)')
            ->pluck('id');

        $testIds = DB::table('engagement_tests')->whereIn('engagement_id', $engIds)->pluck('id');
        $findingIds = DB::table('findings')->whereIn('test_id', $testIds)->pluck('id');

        DB::table('finding_atividades')->whereIn('finding_id', $findingIds)->delete();
        DB::table('findings')->whereIn('id', $findingIds)->delete();
        DB::table('engagement_tests')->whereIn('id', $testIds)->delete();
        DB::table('engagements')->whereIn('id', $engIds)->delete();
    }
};
