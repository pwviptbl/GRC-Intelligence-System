"""
Módulo de conexão e inicialização do banco de dados SQLite.
Cria as tabelas automaticamente se não existirem.
"""
import sqlite3
import os
from contextlib import contextmanager
from datetime import datetime

# Caminho do banco de dados na raiz do projeto
DB_PATH = os.path.join(os.path.dirname(os.path.dirname(__file__)), "grc.db")


def get_connection() -> sqlite3.Connection:
    """Retorna uma conexão com o banco de dados com row_factory configurado."""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row  # Permite acesso por nome de coluna
    conn.execute("PRAGMA foreign_keys = ON")  # Habilita chaves estrangeiras
    return conn


@contextmanager
def get_db():
    """Context manager para gerenciar conexões de forma segura."""
    conn = get_connection()
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def init_db():
    """Inicializa o banco de dados criando todas as tabelas necessárias."""
    with get_db() as conn:
        cursor = conn.cursor()

        # Tabela de clientes
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS clientes (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                nome      TEXT NOT NULL UNIQUE,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Tabela de softwares
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS softwares (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nome       TEXT NOT NULL UNIQUE,
                git_url    TEXT,
                tecnologia TEXT,
                criado_em  DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Tabela de ligação instancias_cliente
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS instancias_cliente (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                cliente_id     INTEGER NOT NULL,
                software_id    INTEGER NOT NULL,
                git_custom_url TEXT,
                branch         TEXT NOT NULL DEFAULT 'master',
                criado_em      DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id)  REFERENCES clientes(id)  ON DELETE CASCADE,
                FOREIGN KEY (software_id) REFERENCES softwares(id) ON DELETE CASCADE
            )
        """)

        # ── Módulo Governança ──────────────────────────────────────────────────

        # Tabela de políticas
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS politicas (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo       TEXT NOT NULL,
                categoria    TEXT NOT NULL,
                versao       TEXT NOT NULL DEFAULT '1.0',
                status       TEXT NOT NULL DEFAULT 'rascunho',
                conteudo     TEXT NOT NULL DEFAULT '',
                criado_em    DATETIME DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Tabela de procedimentos
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS procedimentos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo       TEXT NOT NULL,
                tipo         TEXT NOT NULL,
                status       TEXT NOT NULL DEFAULT 'rascunho',
                criado_em    DATETIME DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Tabela de etapas dos procedimentos
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS procedimento_etapas (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                procedimento_id  INTEGER NOT NULL,
                ordem            INTEGER NOT NULL DEFAULT 1,
                nome_etapa       TEXT NOT NULL,
                responsavel      TEXT NOT NULL DEFAULT '',
                descricao        TEXT NOT NULL DEFAULT '',
                sla              TEXT NOT NULL DEFAULT '',
                FOREIGN KEY (procedimento_id) REFERENCES procedimentos(id) ON DELETE CASCADE
            )
        """)

        # ── Módulo Riscos ──────────────────────────────────────────────────────

        # Tabela de riscos
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS riscos (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo          TEXT NOT NULL,
                descricao       TEXT NOT NULL DEFAULT '',
                origem          TEXT NOT NULL DEFAULT 'Técnico',
                ativo_afetado   TEXT NOT NULL DEFAULT '',
                probabilidade   TEXT NOT NULL DEFAULT 'Media',
                impacto         TEXT NOT NULL DEFAULT 'Medio',
                criticidade     TEXT NOT NULL DEFAULT 'Medio',
                status          TEXT NOT NULL DEFAULT 'aberto',
                politica_ref    TEXT NOT NULL DEFAULT '',
                procedimento_ref TEXT NOT NULL DEFAULT '',
                plano_acao      TEXT NOT NULL DEFAULT '',
                responsavel     TEXT NOT NULL DEFAULT '',
                criado_em       DATETIME DEFAULT CURRENT_TIMESTAMP,
                atualizado_em   DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Tabela de histórico de ações por risco
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS risco_historico (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                risco_id  INTEGER NOT NULL,
                comentario TEXT NOT NULL,
                autor     TEXT NOT NULL DEFAULT 'Analista',
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (risco_id) REFERENCES riscos(id) ON DELETE CASCADE
            )
        """)

        # ── Módulo Autenticação ────────────────────────────────────────────────

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS usuarios (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                username  TEXT NOT NULL UNIQUE,
                senha     TEXT NOT NULL,
                nome      TEXT NOT NULL DEFAULT 'Administrador',
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Cria o usuário padrão se não existir
        from werkzeug.security import generate_password_hash
        user_padrao = os.getenv("DEFAULT_USER", "admin")
        senha_padrao = os.getenv("DEFAULT_PASSWORD", "admin123")
        existe = cursor.execute("SELECT 1 FROM usuarios WHERE username = ?", (user_padrao,)).fetchone()
        if not existe:
            hash_senha = generate_password_hash(senha_padrao)
            cursor.execute(
                "INSERT INTO usuarios (username, senha, nome) VALUES (?, ?, ?)",
                (user_padrao, hash_senha, "Administrador")
            )
            print(f"[AUTH] Usuário padrão '{user_padrao}' criado.")

        # ── Módulo Incidentes ──────────────────────────────────────────────────

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS incidentes (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo           TEXT NOT NULL,
                descricao        TEXT NOT NULL DEFAULT '',
                severidade       TEXT NOT NULL DEFAULT 'Media',
                status           TEXT NOT NULL DEFAULT 'aberto',
                detectado_por    TEXT NOT NULL DEFAULT '',
                data_deteccao    TEXT NOT NULL DEFAULT '',
                risco_vinculado  TEXT NOT NULL DEFAULT '',
                licoes_aprendidas TEXT NOT NULL DEFAULT '',
                criado_em        DATETIME DEFAULT CURRENT_TIMESTAMP,
                atualizado_em    DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS incidente_timeline (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                incidente_id INTEGER NOT NULL,
                acao         TEXT NOT NULL,
                responsavel  TEXT NOT NULL DEFAULT '',
                criado_em    DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (incidente_id) REFERENCES incidentes(id) ON DELETE CASCADE
            )
        """)

        # ── Módulo Plano de Ação ───────────────────────────────────────────────

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS plano_acoes (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo       TEXT NOT NULL,
                descricao    TEXT NOT NULL DEFAULT '',
                origem       TEXT NOT NULL DEFAULT '',
                origem_id    INTEGER,
                responsavel  TEXT NOT NULL DEFAULT '',
                prioridade   TEXT NOT NULL DEFAULT 'media',
                status       TEXT NOT NULL DEFAULT 'pendente',
                criado_em    DATETIME DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # ── Módulo Conformidade LGPD ───────────────────────────────────────────

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS lgpd_itens (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                artigo      TEXT NOT NULL,
                descricao   TEXT NOT NULL,
                categoria   TEXT NOT NULL DEFAULT 'Geral',
                conforme    TEXT NOT NULL DEFAULT 'nao_avaliado',
                observacao  TEXT NOT NULL DEFAULT '',
                evidencia   TEXT NOT NULL DEFAULT '',
                atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Insere os itens padrão LGPD se a tabela estiver vazia
        count = cursor.execute("SELECT COUNT(*) FROM lgpd_itens").fetchone()[0]
        if count == 0:
            itens_lgpd = [
                ("Art. 6, I", "Finalidade: tratamento para propósitos legítimos, específicos e informados", "Princípios"),
                ("Art. 6, II", "Adequação: compatibilidade do tratamento com as finalidades informadas", "Princípios"),
                ("Art. 6, III", "Necessidade: limitação ao mínimo necessário para finalidade", "Princípios"),
                ("Art. 6, IV", "Livre acesso: garantia de consulta facilitada sobre tratamento e dados", "Princípios"),
                ("Art. 6, V", "Qualidade dos dados: exatidão, clareza e atualização dos dados", "Princípios"),
                ("Art. 6, VI", "Transparência: informações claras e acessíveis sobre o tratamento", "Princípios"),
                ("Art. 6, VII", "Segurança: medidas técnicas e administrativas de proteção dos dados", "Princípios"),
                ("Art. 6, VIII", "Prevenção: medidas para prevenir danos aos titulares", "Princípios"),
                ("Art. 6, IX", "Não discriminação: impossibilidade de tratamento discriminatório", "Princípios"),
                ("Art. 6, X", "Responsabilização: demonstração de medidas eficazes de conformidade", "Princípios"),
                ("Art. 7", "Bases legais para o tratamento de dados pessoais", "Bases Legais"),
                ("Art. 8", "Consentimento do titular (quando aplicável)", "Bases Legais"),
                ("Art. 9", "Direito à informação sobre tratamento de dados", "Direitos do Titular"),
                ("Art. 11", "Tratamento de dados pessoais sensíveis", "Dados Sensíveis"),
                ("Art. 14", "Tratamento de dados de crianças e adolescentes", "Dados Sensíveis"),
                ("Art. 15", "Término do tratamento de dados pessoais", "Ciclo de Vida"),
                ("Art. 16", "Eliminação de dados após término do tratamento", "Ciclo de Vida"),
                ("Art. 18", "Direitos do titular: confirmação, acesso, correção, portabilidade", "Direitos do Titular"),
                ("Art. 37", "Registro das operações de tratamento de dados", "Governança"),
                ("Art. 38", "Relatório de impacto à proteção de dados pessoais", "Governança"),
                ("Art. 41", "Encarregado de proteção de dados (DPO)", "Governança"),
                ("Art. 46", "Medidas de segurança para proteção de dados pessoais", "Segurança"),
                ("Art. 47", "Responsabilidade dos agentes de tratamento pela segurança", "Segurança"),
                ("Art. 48", "Comunicação de incidentes de segurança à ANPD e titulares", "Incidentes"),
                ("Art. 49", "Sistemas de tratamento devem atender requisitos de segurança", "Segurança"),
                ("Art. 50", "Boas práticas e governança em proteção de dados", "Governança"),
            ]
            for artigo, descricao, categoria in itens_lgpd:
                cursor.execute(
                    "INSERT INTO lgpd_itens (artigo, descricao, categoria) VALUES (?, ?, ?)",
                    (artigo, descricao, categoria)
                )
            print(f"[LGPD] {len(itens_lgpd)} itens de conformidade inseridos.")

        # ── Módulo Treinamentos ────────────────────────────────────────────────

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS treinamentos (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo       TEXT NOT NULL,
                descricao    TEXT NOT NULL DEFAULT '',
                categoria    TEXT NOT NULL DEFAULT 'Segurança',
                obrigatorio  INTEGER NOT NULL DEFAULT 0,
                criado_em    DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        cursor.execute("""
            CREATE TABLE IF NOT EXISTS treinamento_registros (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                treinamento_id INTEGER NOT NULL,
                colaborador    TEXT NOT NULL,
                status         TEXT NOT NULL DEFAULT 'pendente',
                data_conclusao TEXT,
                criado_em      DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (treinamento_id) REFERENCES treinamentos(id) ON DELETE CASCADE
            )
        """)

    print(f"[DB] Banco de dados inicializado em: {DB_PATH}")

