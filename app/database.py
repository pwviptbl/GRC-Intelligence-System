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

    print(f"[DB] Banco de dados inicializado em: {DB_PATH}")

