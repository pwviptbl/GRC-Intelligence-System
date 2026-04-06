<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\LgpdItem;
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
        // 0. Usuário Admin
        User::create([
            'nome' => 'Administrador',
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin123'),
        ]);

        // 1. Clientes
        $clientes = ['Prefeitura de SP', 'Câmara Mun. BH', 'Instituto XYZ', 'Secretaria Finanças RJ', 'Autarquia de Água'];
        foreach ($clientes as $c) {
            \App\Models\Cliente::create(['nome' => $c]);
        }

        // 2. Softwares
        $softwares = [
            ['nome' => 'e-Cidade', 'tecnologia' => 'PHP 7.4', 'git_url' => 'https://github.com/dbseller/e-cidade'],
            ['nome' => 'Portal Transparência', 'tecnologia' => 'Node.js', 'git_url' => 'https://github.com/dbseller/portal'],
            ['nome' => 'EducaDigital', 'tecnologia' => 'Python/Django', 'git_url' => 'https://github.com/dbseller/educa'],
            ['nome' => 'Saúde Mais', 'tecnologia' => 'Java', 'git_url' => 'https://github.com/dbseller/saude'],
        ];
        foreach ($softwares as $s) {
            \App\Models\Software::create($s);
        }

        // 3. Instâncias (Vínculos aleatórios)
        $cliIds = \App\Models\Cliente::pluck('id')->toArray();
        $softIds = \App\Models\Software::pluck('id')->toArray();
        $branches = ['master', 'v2', 'producao', 'homolog', 'feature/xp'];

        for ($i = 0; $i < 12; $i++) {
            \App\Models\InstanciaCliente::create([
                'cliente_id' => $cliIds[array_rand($cliIds)],
                'software_id' => $softIds[array_rand($softIds)],
                'branch' => $branches[array_rand($branches)],
            ]);
        }

        // 4. Políticas
        \App\Models\Politica::create([
            'titulo' => 'Política de Senhas Fortes',
            'conteudo' => 'Nesta política as senhas devem ter 12 caracteres, incluir símbolos e números.',
            'status' => 'publicado',
            'categoria' => 'Segurança',
            'versao' => '1.0'
        ]);
        \App\Models\Politica::create([
            'titulo' => 'Política de Uso Aceitável (PUA)',
            'conteudo' => 'Regras de uso dos ativos da empresa e conduta digital.',
            'status' => 'publicado',
            'categoria' => 'Conduta',
            'versao' => '2.0'
        ]);

        // 5. Riscos
        $riscos = [
            ['titulo' => 'Acesso SSH legado', 'descricao' => 'Servidores acessíveis com senha fraca', 'probabilidade' => 'Baixo', 'impacto' => 'Alto', 'criticidade' => 'Critico', 'origem' => 'Técnico', 'ativo_afetado' => 'Servidor WS1', 'status' => 'aberto'],
            ['titulo' => 'Backup sem criptografia', 'descricao' => 'Discos de fita saindo sem criptografia', 'probabilidade' => 'Media', 'impacto' => 'Alto', 'criticidade' => 'Alto', 'origem' => 'Processos', 'ativo_afetado' => 'Storage', 'status' => 'aberto'],
            ['titulo' => 'Treinamento LGPD atrasado', 'descricao' => 'Colaboradores novos sem treino', 'probabilidade' => 'Alta', 'impacto' => 'Medio', 'criticidade' => 'Medio', 'origem' => 'Pessoas', 'ativo_afetado' => 'RH', 'status' => 'em_tratamento'],
        ];
        foreach ($riscos as $r) {
            \App\Models\Risco::create($r);
        }

        // 6. Incidentes
        \App\Models\Incidente::create([
            'titulo' => 'Ransomware no setor contábil',
            'descricao' => 'Máquina criptografada e ameaça ao file server.',
            'severidade' => 'Critica',
            'status' => 'contido',
            'detectado_por' => 'Antivírus EDR',
            'data_deteccao' => now()->subDays(2),
            'licoes_aprendidas' => 'Melhorar o backup offline e treinar equipe sobre anexos de e-mail.'
        ]);

        // 7. Plano de Ações
        \App\Models\PlanoAcao::create([
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
            \App\Models\LgpdItem::create([
                'artigo' => $item[0],
                'descricao' => $item[1],
                'categoria' => $item[2],
                'conforme' => 'nao_avaliado',
                'observacao' => '',
                'evidencia' => ''
            ]);
        }

        // 9. Treinamentos
        $t1 = \App\Models\Treinamento::create([
            'titulo' => 'Conscientização Anti-Phishing',
            'descricao' => 'Simulados e boas práticas para evitar golpes de engenharia social.',
            'categoria' => 'Segurança',
            'obrigatorio' => true
        ]);
        for ($i = 0; $i < 5; $i++) {
            \App\Models\TreinamentoRegistro::create(['treinamento_id' => $t1->id, 'colaborador' => "Colaborador $i", 'status' => 'pendente']);
        }
    }
}
