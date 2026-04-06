<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LgpdItem;
use App\Models\Cliente;
use App\Models\Software;
use App\Models\InstanciaCliente;
use App\Models\Politica;
use App\Models\Risco;
use App\Models\Incidente;
use App\Models\PlanoAcao;
use App\Models\Treinamento;
use App\Models\TreinamentoRegistro;
use App\Models\Procedimento;
use App\Models\ProcedimentoEtapa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Usuário Admin (Garante que não duplique)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'nome' => 'Administrador',
                'name' => 'Administrador',
                'email' => 'admin@admin.com',
                'password' => Hash::make('admin123'),
            ]
        );

        // Limpa tabelas antes de repopular
        DB::statement('TRUNCATE TABLE clientes CASCADE');
        DB::statement('TRUNCATE TABLE software CASCADE');
        DB::statement('TRUNCATE TABLE politicas CASCADE');
        DB::statement('TRUNCATE TABLE riscos CASCADE');
        DB::statement('TRUNCATE TABLE incidentes CASCADE');
        DB::statement('TRUNCATE TABLE plano_acaos CASCADE');
        DB::statement('TRUNCATE TABLE lgpd_items CASCADE');
        DB::statement('TRUNCATE TABLE treinamentos CASCADE');
        DB::statement('TRUNCATE TABLE procedimentos CASCADE');
        DB::statement('TRUNCATE TABLE procedimento_etapas CASCADE');

        // 1. Clientes
        $clientes = ['Prefeitura de SP', 'Câmara Mun. BH', 'Instituto XYZ', 'Secretaria Finanças RJ', 'Autarquia de Água'];
        foreach ($clientes as $c) {
            Cliente::create(['nome' => $c]);
        }

        // 2. Softwares
        $softwares = [
            ['nome' => 'e-Cidade', 'tecnologia' => 'PHP 7.4', 'git_url' => 'https://github.com/dbseller/e-cidade'],
            ['nome' => 'Portal Transparência', 'tecnologia' => 'Node.js', 'git_url' => 'https://github.com/dbseller/portal'],
            ['nome' => 'EducaDigital', 'tecnologia' => 'Python/Django', 'git_url' => 'https://github.com/dbseller/educa'],
            ['nome' => 'Saúde Mais', 'tecnologia' => 'Java', 'git_url' => 'https://github.com/dbseller/saude'],
        ];
        foreach ($softwares as $s) {
            Software::create($s);
        }

        // 3. Instâncias
        $cliIds = Cliente::pluck('id')->toArray();
        $softIds = Software::pluck('id')->toArray();
        $branches = ['master', 'v2', 'producao', 'homolog', 'feature/xp'];

        for ($i = 0; $i < 12; $i++) {
            InstanciaCliente::create([
                'cliente_id' => $cliIds[array_rand($cliIds)],
                'software_id' => $softIds[array_rand($softIds)],
                'branch' => $branches[array_rand($branches)],
            ]);
        }

        // 4. Políticas
        Politica::create([
            'titulo' => 'Política de Senhas Fortes',
            'conteudo' => "Esta política estabelece os requisitos mínimos para senhas...\n\n1. Comprimento mínimo de 12 caracteres.\n2. Uso de símbolos e números.\n3. Troca obrigatória a cada 90 dias.",
            'status' => 'publicado',
            'categoria' => 'Segurança',
            'versao' => '1.0'
        ]);
        Politica::create([
            'titulo' => 'Política de Uso Aceitável (PUA)',
            'conteudo' => "Regras de conduta para uso dos ativos tecnológicos da empresa...",
            'status' => 'publicado',
            'categoria' => 'Conduta',
            'versao' => '2.0'
        ]);

        // 5. Riscos
        $riscos = [
            ['titulo' => 'Acesso SSH legado', 'descricao' => 'Servidores acessíveis com senha fraca', 'probabilidade' => 'Alta', 'impacto' => 'Alto', 'criticidade' => 'Critico', 'origem' => 'Técnico', 'ativo_afetado' => 'Servidor WS1', 'status' => 'aberto', 'plano_acao' => 'Implementar autenticação via chave SSH e desabilitar senhas.'],
            ['titulo' => 'Backup sem criptografia', 'descricao' => 'Discos de fita saindo sem criptografia', 'probabilidade' => 'Media', 'impacto' => 'Alto', 'criticidade' => 'Alto', 'origem' => 'Processos', 'ativo_afetado' => 'Storage', 'status' => 'aberto', 'plano_acao' => 'Ativar AES-256 no software de backup.'],
            ['titulo' => 'Treinamento LGPD atrasado', 'descricao' => 'Colaboradores novos sem treino', 'probabilidade' => 'Alta', 'impacto' => 'Medio', 'criticidade' => 'Medio', 'origem' => 'Pessoas', 'ativo_afetado' => 'RH', 'status' => 'em_tratamento', 'plano_acao' => 'Incluir o treinamento no fluxo de admissão.'],
        ];
        foreach ($riscos as $r) {
            Risco::create($r);
        }

        // 6. Incidentes
        Incidente::create([
            'titulo' => 'Ransomware no setor contábil',
            'descricao' => 'Máquina criptografada e ameaça ao file server.',
            'severidade' => 'Critica',
            'status' => 'contido',
            'detectado_por' => 'Antivírus EDR',
            'data_deteccao' => now()->subDays(2),
            'licoes_aprendidas' => 'Melhorar o backup offline e treinar equipe sobre anexos de e-mail.'
        ]);

        // 7. Plano de Ações
        PlanoAcao::create([
            'titulo' => 'Trocar algoritmo de Hash no Login',
            'descricao' => 'Mudar de MD5 para Argon2',
            'origem' => 'Risco: Sistema Legado',
            'responsavel' => 'Dev Backend',
            'prioridade' => 'critica',
            'status' => 'pendente'
        ]);

        // 8. LGPD Checklist
        $itens_lgpd = [
            ["Art. 37", "Registro das operações de tratamento de dados", "Governança"],
            ["Art. 38", "Relatório de impacto à proteção de dados pessoais", "Governança"],
            ["Art. 41", "Encarregado de proteção de dados (DPO)", "Governança"],
            ["Art. 46", "Medidas de segurança para proteção de dados pessoais", "Segurança"],
            ["Art. 48", "Comunicação de incidentes de segurança à ANPD", "Incidentes"],
        ];
        foreach ($itens_lgpd as $item) {
            LgpdItem::create([
                'artigo' => $item[0],
                'descricao' => $item[1],
                'categoria' => $item[2],
                'conforme' => 'nao_avaliado',
                'observacao' => '',
                'evidencia' => ''
            ]);
        }

        // 9. Treinamentos
        $t1 = Treinamento::create([
            'titulo' => 'Conscientização Anti-Phishing',
            'descricao' => 'Simulados e boas práticas para evitar golpes de engenharia social.',
            'categoria' => 'Segurança',
            'obrigatorio' => true
        ]);
        for ($i = 0; $i < 5; $i++) {
            TreinamentoRegistro::create(['treinamento_id' => $t1->id, 'colaborador' => "Colaborador $i", 'status' => 'pendente']);
        }

        // 10. Procedimentos
        $proc1 = Procedimento::create([
            'titulo' => 'Resposta a Vazamento de Dados',
            'tipo' => 'Incidente',
            'status' => 'publicado'
        ]);

        ProcedimentoEtapa::create([
            'procedimento_id' => $proc1->id,
            'ordem' => 1,
            'nome_etapa' => 'Identificação e Triagem',
            'responsavel' => 'Equipe SOC',
            'descricao' => 'Confirmar a autenticidade do vazamento e identificar a origem dos dados.',
            'sla' => '2 horas'
        ]);

        ProcedimentoEtapa::create([
            'procedimento_id' => $proc1->id,
            'ordem' => 2,
            'nome_etapa' => 'Contenção',
            'responsavel' => 'Infraestrutura / Segurança',
            'descricao' => 'Isolar sistemas afetados e revogar credenciais comprometidas.',
            'sla' => '4 horas'
        ]);

        $proc2 = Procedimento::create([
            'titulo' => 'Onboarding de Segurança - Novos Colaboradores',
            'tipo' => 'Administrativo',
            'status' => 'publicado'
        ]);

        ProcedimentoEtapa::create([
            'procedimento_id' => $proc2->id,
            'ordem' => 1,
            'nome_etapa' => 'Entrega de Ativos',
            'responsavel' => 'TI Suporte',
            'descricao' => 'Configuração da máquina com criptografia de disco e antivírus.',
            'sla' => '24 horas'
        ]);
    }
}
