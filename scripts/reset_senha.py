#!/usr/bin/env python3
"""
Script de reset de senha do GRC Intelligence System.
Gera uma senha aleatória e atualiza no banco de dados.

Uso:
    python reset_senha.py               # Reseta a senha do primeiro usuário (admin)
    python reset_senha.py admin          # Reseta a senha de um usuário específico
"""
import os
import sys
import string
import secrets
import sqlite3

# Caminho do banco de dados
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(SCRIPT_DIR, "grc.db")


def gerar_senha(tamanho=12):
    """Gera uma senha aleatória segura."""
    caracteres = string.ascii_letters + string.digits + "!@#$%"
    return ''.join(secrets.choice(caracteres) for _ in range(tamanho))


def main():
    if not os.path.exists(DB_PATH):
        print(f"❌ Banco de dados não encontrado em: {DB_PATH}")
        print("   Execute o servidor pelo menos uma vez para criar o banco.")
        sys.exit(1)

    # Determina o usuário alvo
    username = sys.argv[1] if len(sys.argv) > 1 else None

    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row

    try:
        if username:
            user = conn.execute("SELECT * FROM usuarios WHERE username = ?", (username,)).fetchone()
        else:
            user = conn.execute("SELECT * FROM usuarios ORDER BY id LIMIT 1").fetchone()

        if not user:
            print(f"❌ Usuário {'«' + username + '»' if username else ''} não encontrado.")
            # Lista os disponíveis
            todos = conn.execute("SELECT username FROM usuarios").fetchall()
            if todos:
                print(f"   Usuários disponíveis: {', '.join(u['username'] for u in todos)}")
            sys.exit(1)

        # Gera hash da nova senha com werkzeug
        from werkzeug.security import generate_password_hash
        nova_senha = gerar_senha()
        hash_senha = generate_password_hash(nova_senha)

        conn.execute("UPDATE usuarios SET senha = ? WHERE id = ?", (hash_senha, user["id"]))
        conn.commit()

        print()
        print("╔══════════════════════════════════════════════╗")
        print("║     GRC Intelligence System - Reset Senha    ║")
        print("╠══════════════════════════════════════════════╣")
        print(f"║  Usuário:     {user['username']:<30} ║")
        print(f"║  Nova Senha:  {nova_senha:<30} ║")
        print("╠══════════════════════════════════════════════╣")
        print("║  ⚠️  Anote esta senha, ela não será mostrada  ║")
        print("║      novamente!                              ║")
        print("╚══════════════════════════════════════════════╝")
        print()

    finally:
        conn.close()


if __name__ == "__main__":
    main()
