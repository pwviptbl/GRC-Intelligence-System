import sqlite3
import random
from datetime import datetime, timedelta

def get_banco():
    return sqlite3.connect('grc.db')

def seed():
    conn = get_banco()
    cursor = conn.cursor()

    print("🛠 Populando dados de GRC para teste...")

    # 1. Ativos (Clientes, Softwares, Instâncias)
    clientes = ['Prefeitura de SP', 'Câmara Mun. BH', 'Instituto XYZ', 'Secretaria Finanças RJ', 'Autarquia de Água']
    softwares = [('e-Cidade', 'PHP 7.4'), ('Portal Transparência', 'Node.js'), ('EducaDigital', 'Python/Django'), ('Saúde Mais', 'Java')]
    
    for c in clientes:
        cursor.execute("INSERT OR IGNORE INTO clientes (nome) VALUES (?)", (c,))
    
    for s, t in softwares:
        cursor.execute("INSERT OR IGNORE INTO softwares (nome, tecnologia) VALUES (?, ?)", (s, t))
    
    conn.commit()

    # Vínculos
    cursor.execute("SELECT id FROM clientes")
    cli_ids = [r[0] for r in cursor.fetchall()]
    cursor.execute("SELECT id FROM softwares")
    soft_ids = [r[0] for r in cursor.fetchall()]

    for i in range(12):
        cliente_id = random.choice(cli_ids)
        software_id = random.choice(soft_ids)
        branch = random.choice(['master', 'v2', 'producao', 'homolog', 'feature/xp'])
        cursor.execute("INSERT OR IGNORE INTO instancias_cliente (cliente_id, software_id, branch) VALUES (?, ?, ?)", (cliente_id, software_id, branch))
    
    # 2. Governança (Políticas e Procedimentos)
    cursor.execute("INSERT OR IGNORE INTO politicas (titulo, conteudo, status, categoria, versao) VALUES (?, ?, ?, ?, ?)",
                   ('Política de Senhas Fortes', 'Nesta política as senhas devem ter 12 caracteres...', 'publicado', 'Segurança', '1.0'))
    cursor.execute("INSERT OR IGNORE INTO politicas (titulo, conteudo, status, categoria, versao) VALUES (?, ?, ?, ?, ?)",
                   ('Política de Uso Aceitável (PUA)', 'Regras de uso dos ativos da empresa.', 'publicado', 'Conduta', '2.0'))
    cursor.execute("INSERT OR IGNORE INTO politicas (titulo, conteudo, status, categoria, versao) VALUES (?, ?, ?, ?, ?)",
                   ('Política de Acesso a Terceiros', 'Restrições para VPN de terceiros.', 'em_revisao', 'Acessos', '1.1'))

    cursor.execute("INSERT OR IGNORE INTO procedimentos (titulo, status, tipo) VALUES (?, ?, ?)",
                   ('Resposta a Vazamento', 'publicado', 'Incidente'))
    cursor.execute("SELECT id FROM procedimentos WHERE titulo='Resposta a Vazamento'")
    proc_row = cursor.fetchone()
    if proc_row:
        cursor.execute("INSERT OR IGNORE INTO procedimento_etapas (procedimento_id, ordem, nome_etapa, responsavel, descricao) VALUES (?, ?, ?, ?, ?)",
                       (proc_row[0], 1, 'Identificar', 'Equipe SOC', 'Confirmar o vazamento.'))

    # 3. Riscos
    riscos = [
        ('Acesso SSH legado', 'Servidores acessíveis com senha fraca', 'Baixo', 'Alto', 'Critico', 'Técnico', 'Servidor WS1', 'aberto'),
        ('Backup sem criptografia', 'Discos de fita saindo sem criptografia', 'Media', 'Alto', 'Alto', 'Processos', 'Storage', 'aberto'),
        ('Treinamento LGPD atrasado', 'Colaboradores novos sem treino', 'Alta', 'Medio', 'Medio', 'Pessoas', 'RH', 'em_tratamento'),
        ('Phishing no financeiro', 'Constantes tentativas de golpe via email', 'Alta', 'Baixo', 'Baixo', 'Técnico', 'Email Financeiro', 'monitorando')
    ]
    for r in riscos:
        cursor.execute("INSERT OR IGNORE INTO riscos (titulo, descricao, probabilidade, impacto, criticidade, origem, ativo_afetado, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", r)

    # 4. Incidentes
    incidentes = [
        ('Ransomware no setor contábil', 'Máquina criptografada e ameaça ao file server.', 'Critica', 'contido', 'Antivírus EDR', (datetime.now() - timedelta(days=2)).strftime('%Y-%m-%d')),
        ('Acesso indevido ao BD', 'Credencial vaza no Github e alguém acessou BD de devs.', 'Alta', 'investigando', 'Alerta Github', (datetime.now() - timedelta(days=5)).strftime('%Y-%m-%d')),
        ('Queda da API pública', 'DDoS de 5 horas.', 'Media', 'fechado', 'Monitoramento', (datetime.now() - timedelta(days=15)).strftime('%Y-%m-%d'))
    ]
    for i in incidentes:
        cursor.execute("INSERT OR IGNORE INTO incidentes (titulo, descricao, severidade, status, detectado_por, data_deteccao) VALUES (?, ?, ?, ?, ?, ?)", i)

    # 5. Ações Pendentes
    acoes = [
        ('Trocar algoritmo de Hash no Login', 'Mudar de MD5 para Argon2', 'Risco: Sistema Legado', 'Dev Backend', 'critica', 'pendente'),
        ('Revisar acessos da equipe de vendas', 'Revogar ex-funcionários', 'Auditoria Anual', 'Admin', 'alta', 'pendente'),
        ('Atualizar Firewall Fortinet', 'Aplicar patch cve-2024-xxxx', 'Incidente de rede', 'Infra', 'alta', 'em_andamento'),
        ('Publicar Política de BYOD', 'Dispositivos pessoais na rede', 'Governança', 'DPO', 'media', 'concluida')
    ]
    for a in acoes:
        cursor.execute("INSERT OR IGNORE INTO plano_acoes (titulo, descricao, origem, responsavel, prioridade, status) VALUES (?, ?, ?, ?, ?, ?)", a)

    # 6. LGPD Checklist
    itens_lgpd = [
        ('Art. 37', 'Manter registro das operações de dados', 'Conformidade', 'conforme', 'Feito via logstash'),
        ('Art. 38', 'Relatório de Impacto à Proteção de Dados', 'Conformidade', 'parcial', 'RIPD apenas para o CORE'),
        ('Art. 41', 'Indicação do Encarregado (DPO)', 'Pessoas', 'nao_conforme', 'Buscando candidato no mercado'),
        ('Art. 46', 'Medidas de segurança aptas a proteger os dados', 'Técnico', 'parcial', 'Antivírus instalado, falta MFA'),
        ('Art. 48', 'Comunicação de incidente de segurança', 'Processos', 'nao_avaliado', '')
    ]
    cursor.execute("DELETE FROM lgpd_itens") # clean base
    for l in itens_lgpd:
        cursor.execute("INSERT OR IGNORE INTO lgpd_itens (artigo, descricao, categoria, conforme, observacao) VALUES (?, ?, ?, ?, ?)", l)

    # 7. Treinamentos
    cursor.execute("INSERT OR IGNORE INTO treinamentos (titulo, descricao, categoria, obrigatorio) VALUES (?, ?, ?, ?)", ('Conscientização Anti-Phishing', 'Simulados e boas práticas.', 'Segurança', True))
    cursor.execute("INSERT OR IGNORE INTO treinamentos (titulo, descricao, categoria, obrigatorio) VALUES (?, ?, ?, ?)", ('Fundamentos LGPD para o suporte', 'Como lidar com dados pessoais nos chamados.', 'LGPD', True))
    
    conn.commit()
    cursor.execute("SELECT id FROM treinamentos")
    treino_ids = [r[0] for r in cursor.fetchall()]
    
    for t_id in treino_ids:
        for x in range(random.randint(2, 6)):
            status = random.choice(['pendente', 'concluido'])
            cursor.execute("INSERT OR IGNORE INTO treinamento_registros (treinamento_id, colaborador, status) VALUES (?, ?, ?)", (t_id, f"Colaborador Teste {x}", status))

    conn.commit()
    conn.close()
    print("✅ Banco de dados populado com sucesso para testes.")

if __name__ == '__main__':
    seed()
