"""
Blueprint de autenticação — login, logout e alteração de senha.
"""
from flask import Blueprint, jsonify, request, session
from werkzeug.security import generate_password_hash, check_password_hash
from app.database import get_db

auth_bp = Blueprint("auth", __name__, url_prefix="/api/auth")


@auth_bp.post("/login")
def login():
    """Autentica o usuário e cria sessão."""
    data = request.get_json(silent=True) or {}
    username = (data.get("username") or "").strip()
    senha    = data.get("senha", "")

    if not username or not senha:
        return jsonify({"erro": "Usuário e senha são obrigatórios."}), 400

    with get_db() as conn:
        user = conn.execute(
            "SELECT * FROM usuarios WHERE username = ?", (username,)
        ).fetchone()

    if not user or not check_password_hash(user["senha"], senha):
        return jsonify({"erro": "Usuário ou senha inválidos."}), 401

    session["user_id"] = user["id"]
    session["username"] = user["username"]
    session["nome"] = user["nome"]

    return jsonify({"ok": True, "username": user["username"], "nome": user["nome"]})


@auth_bp.post("/logout")
def logout():
    """Encerra a sessão do usuário."""
    session.clear()
    return jsonify({"ok": True})


@auth_bp.get("/me")
def me():
    """Retorna dados do usuário logado (ou 401)."""
    if "user_id" not in session:
        return jsonify({"erro": "Não autenticado."}), 401
    return jsonify({"username": session["username"], "nome": session["nome"]})


@auth_bp.post("/senha")
def alterar_senha():
    """Altera a senha do usuário logado."""
    if "user_id" not in session:
        return jsonify({"erro": "Não autenticado."}), 401

    data = request.get_json(silent=True) or {}
    senha_atual = data.get("senha_atual", "")
    senha_nova  = data.get("senha_nova", "")

    if not senha_atual or not senha_nova:
        return jsonify({"erro": "Senha atual e nova são obrigatórias."}), 400
    if len(senha_nova) < 4:
        return jsonify({"erro": "A nova senha deve ter pelo menos 4 caracteres."}), 400

    with get_db() as conn:
        user = conn.execute(
            "SELECT * FROM usuarios WHERE id = ?", (session["user_id"],)
        ).fetchone()
        if not check_password_hash(user["senha"], senha_atual):
            return jsonify({"erro": "Senha atual incorreta."}), 401

        conn.execute(
            "UPDATE usuarios SET senha = ? WHERE id = ?",
            (generate_password_hash(senha_nova), session["user_id"])
        )

    return jsonify({"ok": True, "mensagem": "Senha alterada com sucesso!"})
