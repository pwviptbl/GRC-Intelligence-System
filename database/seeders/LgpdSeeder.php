<?php

namespace Database\Seeders;

use App\Models\LgpdItem;
use Illuminate\Database\Seeder;

class LgpdSeeder extends Seeder
{
    public function run(): void
    {
        $itens = [
            [
                'artigo' => 'Art. 6',
                'titulo' => 'Princípios do Tratamento',
                'categoria' => 'Conformidade',
                'descricao' => "**O que é?** São as 10 regras de ouro da LGPD (Finalidade, Adequação, Necessidade, Livre Acesso, Qualidade, Transparência, Segurança, Prevenção, Não Discriminação e Responsabilização).\n\n**Como fazer?** Auditar se a empresa coleta apenas o mínimo necessário para a finalidade específica. Se houver excesso de dados (ex: pedir CPF para baixar um e-book sem motivo), há uma violação do princípio da necessidade."
            ],
            [
                'artigo' => 'Art. 7',
                'titulo' => 'Bases Legais para Tratamento',
                'categoria' => 'Jurídico',
                'descricao' => "**O que é?** É a justificativa jurídica que permite à empresa tratar dados pessoais. Ninguém pode tratar dados sem uma base legal definida.\n\n**Como fazer?** Analisar cada processo (ex: RH, Vendas) e mapear se ele se encaixa em Consentimento, Execução de Contrato, Legítimo Interesse ou Obrigação Legal. Documente essa escolha no ROPA."
            ],
            [
                'artigo' => 'Art. 11',
                'titulo' => 'Dados Pessoais Sensíveis',
                'categoria' => 'Jurídico',
                'descricao' => "**O que é?** Dados sobre origem racial, convicção religiosa, opinião política, saúde ou vida sexual. Têm regras muito mais rígidas de proteção.\n\n**Como fazer?** Identificar se a empresa trata esses dados (ex: biometria, atestados médicos). Verificar se há uma camada extra de segurança e se a base legal utilizada é compatível com o Art. 11."
            ],
            [
                'artigo' => 'Art. 15 e 16',
                'titulo' => 'Término e Descarte de Dados',
                'categoria' => 'Operacional',
                'descricao' => "**O que é?** A obrigação de excluir os dados após a finalidade ser alcançada ou o contrato encerrado.\n\n**Como fazer?** Verificar se a empresa possui uma Política de Retenção e Descarte. Dados não podem ficar guardados 'para sempre' sem uma justificativa legal de guarda (como leis trabalhistas ou fiscais)."
            ],
            [
                'artigo' => 'Art. 18',
                'titulo' => 'Direitos do Titular',
                'categoria' => 'Operacional',
                'descricao' => "**O que é?** É a capacidade da empresa de atender pedidos dos cidadãos (acesso, correção, exclusão ou portabilidade de seus dados).\n\n**Como fazer?** Criar um canal oficial (ex: e-mail do DPO ou formulário no site) e um procedimento interno que garanta a resposta em até 15 dias conforme a lei."
            ],
            [
                'artigo' => 'Art. 33',
                'titulo' => 'Transferência Internacional',
                'categoria' => 'TI / Infra',
                'descricao' => "**O que é?** O envio de dados para servidores fora do Brasil (ex: Nuvem AWS nos EUA ou Google Cloud).\n\n**Como fazer?** Verificar se os fornecedores de nuvem possuem certificações de segurança e se os contratos de serviço possuem cláusulas padrão de proteção de dados (Standard Contractual Clauses)."
            ],
            [
                'artigo' => 'Art. 37',
                'titulo' => 'Registro de Operações (ROPA)',
                'categoria' => 'Governança',
                'descricao' => "**O que é?** Um inventário detalhado de todo o ciclo de vida do dado dentro da empresa: quem coleta, onde armazena, com quem compartilha e quando exclui.\n\n**Como fazer?** Manter uma planilha ou software atualizado contendo o mapeamento de processos de todos os setores que lidam com dados pessoais."
            ],
            [
                'artigo' => 'Art. 38',
                'titulo' => 'Relatório de Impacto (RIPD)',
                'categoria' => 'Governança',
                'descricao' => "**O que é?** Documento que descreve os processos de tratamento de dados que podem gerar riscos às liberdades civis e aos direitos fundamentais.\n\n**Como fazer?** Realizar uma avaliação de risco para processos críticos (ex: monitoramento de funcionários ou uso de IA) e documentar as medidas de mitigação tomadas."
            ],
            [
                'artigo' => 'Art. 39',
                'titulo' => 'Operadores e Contratos',
                'categoria' => 'Jurídico',
                'descricao' => "**O que é?** A responsabilidade sobre as empresas parceiras que processam dados em nome da sua empresa (ex: contabilidade externa, software de folha).\n\n**Como fazer?** Revisar contratos com fornecedores para incluir o 'Adendo de Processamento de Dados' (DPA), garantindo que eles também cumpram a LGPD."
            ],
            [
                'artigo' => 'Art. 41',
                'titulo' => 'Nomeação do Encarregado (DPO)',
                'categoria' => 'Governança',
                'descricao' => "**O que é?** A indicação formal de uma pessoa (interna ou externa) que servirá de canal de comunicação entre a empresa, os titulares e a ANPD.\n\n**Como fazer?** Publicar no site da empresa a identidade e as informações de contato do Encarregado de forma clara e objetiva."
            ],
            [
                'artigo' => 'Art. 46',
                'titulo' => 'Medidas de Segurança',
                'categoria' => 'Segurança',
                'descricao' => "**O que é?** O uso de tecnologias e processos para proteger os dados contra acessos não autorizados, destruição ou perda.\n\n**Como fazer?** Implementar criptografia, controle de acessos (quem pode ver o quê), antivírus, firewall e políticas de senhas fortes em todos os sistemas que tratam dados."
            ],
            [
                'artigo' => 'Art. 47',
                'titulo' => 'Privacy by Design',
                'categoria' => 'Segurança',
                'descricao' => "**O que é?** É a garantia de que a proteção de dados seja pensada desde a concepção de um novo sistema ou projeto.\n\n**Como fazer?** Incluir requisitos de privacidade em todos os novos desenvolvimentos de software e limitar a coleta ao mínimo necessário (minimização)."
            ],
            [
                'artigo' => 'Art. 48',
                'titulo' => 'Comunicação de Incidentes',
                'categoria' => 'Incidentes',
                'descricao' => "**O que é?** A obrigação de avisar à ANPD e aos titulares caso ocorra um vazamento que possa gerar risco ou dano relevante.\n\n**Como fazer?** Ter um Plano de Resposta a Incidentes pronto, com prazos e modelos de notificação definidos para agir rapidamente."
            ],
            [
                'artigo' => 'Art. 50',
                'titulo' => 'Boas Práticas e Governança',
                'categoria' => 'Governança',
                'descricao' => "**O que é?** A existência de um Programa de Privacidade estruturado, com treinamentos regulares e políticas internas.\n\n**Como fazer?** Realizar treinamentos anuais com a equipe, ter políticas publicadas e realizar auditorias periódicas como esta."
            ]
        ];

        foreach ($itens as $item) {
            LgpdItem::updateOrCreate(
                ['artigo' => $item['artigo']],
                [
                    'descricao' => $item['descricao'],
                    'categoria' => $item['categoria'],
                    'evidencia' => $item['titulo'],
                    'conforme' => 'nao_avaliado',
                    'observacao' => ''
                ]
            );
        }
    }
}
