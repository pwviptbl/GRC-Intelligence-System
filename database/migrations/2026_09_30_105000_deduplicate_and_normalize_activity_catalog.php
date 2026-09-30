<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('atividades')) {
            return;
        }

        $mergeGroups = [
            // 1. Gestão de Dependências (SCA)
            [
                'id' => 52,
                'nome' => 'Gestão de Dependências (SCA)',
                'categoria' => 'SCA / Dependências',
                'merge_from' => [2, 6, 17, 22, 27, 32, 37, 47, 57, 62, 67, 71, 81, 87, 132, 135, 138, 147, 149],
            ],
            // 2. SAST e Code Review
            [
                'id' => 98,
                'nome' => 'SAST e Code Review (Scripts próprios / PHPStan)',
                'categoria' => 'SAST / Análise Estática',
                'merge_from' => [129, 131, 134, 137, 140, 146],
            ],
            // 3. Scan Dinâmico (DAST)
            [
                'id' => 93,
                'nome' => 'Scan Dinâmico de Aplicação (DAST)',
                'categoria' => 'DAST / Análise Dinâmica',
                'merge_from' => [111, 115, 119, 123, 127, 144],
            ],
            // 4. Scan Perimetral (Nmap)
            [
                'id' => 91,
                'nome' => 'Scan Perimetral e Bloqueio de Portas (Nmap)',
                'categoria' => 'Infraestrutura / Rede',
                'merge_from' => [109, 113, 117, 121, 125, 142],
            ],
            // 5. Teste Manual de Restauração de Backup e DR
            [
                'id' => 130,
                'nome' => 'Teste Manual de Restauração de Backup e DR',
                'categoria' => 'Continuidade / DR',
                'merge_from' => [133, 136, 139, 141, 148],
            ],
            // 6. Teste Manual de Segurança
            [
                'id' => 95,
                'nome' => 'Teste Manual de Segurança e Lógica de Negócio',
                'categoria' => 'Pentest / Manual',
                'merge_from' => [112, 116, 120, 124, 128, 145],
            ],
            // 7. Validação de Bloqueio a Diretórios Internos via URL
            [
                'id' => 92,
                'nome' => 'Validação de Bloqueio a Diretórios Internos via URL',
                'categoria' => 'Hardening / Acesso',
                'merge_from' => [110, 114, 118, 122, 126, 143],
            ],
            // 8. Pentest Interno Geral
            [
                'id' => 1,
                'nome' => 'Pentest Interno Geral de Aplicação',
                'categoria' => 'Pentest / Aplicação',
                'merge_from' => [4],
            ],
            // 9. Pentest Upload de Arquivos
            [
                'id' => 13,
                'nome' => 'Pentest de Upload de Arquivos e Anexos',
                'categoria' => 'Pentest / Funcional',
                'merge_from' => [34, 40],
            ],
            // 10. Pentest Autenticação e Sessão
            [
                'id' => 23,
                'nome' => 'Pentest de Autenticação, Sessão e Tokens',
                'categoria' => 'Pentest / Autenticação',
                'merge_from' => [28, 29, 30, 41, 58, 63, 82],
            ],
            // 11. Pentest Autorização e Controles Administrativos
            [
                'id' => 14,
                'nome' => 'Pentest de Autorização, Perfis e Acesso Administrativo',
                'categoria' => 'Pentest / Autorização',
                'merge_from' => [16, 24, 26, 84],
            ],
            // 12. Pentest Integrações e APIs
            [
                'id' => 15,
                'nome' => 'Pentest de APIs, Webhooks e Integrações',
                'categoria' => 'Pentest / Integração',
                'merge_from' => [25, 31, 36, 46, 55, 61, 68, 86],
            ],
            // 13. Pentest Exposição de Dados e Relatórios
            [
                'id' => 20,
                'nome' => 'Pentest de Exposição de Dados, Relatórios e Consultas',
                'categoria' => 'Pentest / Dados',
                'merge_from' => [43, 44, 45, 59, 60, 64, 66, 69, 70],
            ],
            // 14. Pentest Regras de Negócio e Validação de Fluxos
            [
                'id' => 5,
                'nome' => 'Pentest de Regras de Negócio e Validação de Fluxos',
                'categoria' => 'Pentest / Negócio',
                'merge_from' => [8, 10, 11, 12, 18, 19, 21, 33, 35, 38, 39, 53, 54, 56, 65, 83, 85],
            ],
        ];

        DB::transaction(function () use ($mergeGroups) {
            foreach ($mergeGroups as $group) {
                $canonicalId = $group['id'];
                $mergeFrom = $group['merge_from'];

                // 1. Redirecionar vínculos de software_modulo_atividades
                if (Schema::hasTable('software_modulo_atividades')) {
                    $existingCanonicalModuloIds = DB::table('software_modulo_atividades')
                        ->where('atividade_id', $canonicalId)
                        ->pluck('software_modulo_id')
                        ->all();

                    // Remove registros antigos onde o módulo já possui a atividade canônica
                    if (! empty($existingCanonicalModuloIds)) {
                        DB::table('software_modulo_atividades')
                            ->whereIn('atividade_id', $mergeFrom)
                            ->whereIn('software_modulo_id', $existingCanonicalModuloIds)
                            ->delete();
                    }

                    // Redireciona os vínculos restantes para a atividade canônica
                    DB::table('software_modulo_atividades')
                        ->whereIn('atividade_id', $mergeFrom)
                        ->update(['atividade_id' => $canonicalId]);
                }

                // 2. Redirecionar controle_eventos
                if (Schema::hasTable('controle_eventos')) {
                    DB::table('controle_eventos')
                        ->whereIn('atividade_id', $mergeFrom)
                        ->update(['atividade_id' => $canonicalId]);
                }

                // 3. Redirecionar finding_atividades
                if (Schema::hasTable('finding_atividades')) {
                    DB::table('finding_atividades')
                        ->whereIn('atividade_id', $mergeFrom)
                        ->update(['atividade_id' => $canonicalId]);
                }

                // 4. Redirecionar riscos
                if (Schema::hasTable('riscos')) {
                    DB::table('riscos')
                        ->whereIn('atividade_id', $mergeFrom)
                        ->update(['atividade_id' => $canonicalId]);
                }

                // 5. Excluir atividades duplicadas
                DB::table('atividades')
                    ->whereIn('id', $mergeFrom)
                    ->delete();

                // 6. Atualizar a atividade canônica
                DB::table('atividades')
                    ->where('id', $canonicalId)
                    ->update([
                        'atividade' => $group['nome'],
                        'categoria' => $group['categoria'],
                        'software_id' => null,
                        'modulo' => null,
                        'tier_minimo' => null,
                        'tier_politica_id' => null,
                        'ativo' => true,
                    ]);
            }

            // Atualizar atividades remanescentes para garantir nomes limpos e campos nulos
            if (DB::table('atividades')->where('id', 3)->exists()) {
                DB::table('atividades')->where('id', 3)->update([
                    'atividade' => 'Análise de Vulnerabilidades Autenticada',
                    'categoria' => 'Pentest / Aplicação',
                ]);
            }

            if (DB::table('atividades')->where('id', 88)->exists()) {
                DB::table('atividades')->where('id', 88)->update([
                    'categoria' => 'Proteção de Aplicação',
                ]);
            }

            if (DB::table('atividades')->where('id', 89)->exists()) {
                DB::table('atividades')->where('id', 89)->update([
                    'categoria' => 'Privacidade de Dados',
                ]);
            }

            // Garantir que 100% das atividades do catálogo tenham software_id e modulo nulos
            DB::table('atividades')->update([
                'software_id' => null,
                'modulo' => null,
                'tier_minimo' => null,
                'tier_politica_id' => null,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // A consolidação e deduplicação de dados históricos é irreversível
    }
};
