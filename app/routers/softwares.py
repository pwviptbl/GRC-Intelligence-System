"""
Blueprint de softwares — CRUD da tabela 'softwares'.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

softwares_bp = Blueprint("softwares", __name__, url_prefix="/api/softwares")


@softwares_bp.get("")
def listar():
    """Lista todos os softwares em ordem alfabética."""
    with get_db() as conn:
        rows = conn.execute(
            "SELECT id, nome, git_url, tecnologia, criado_em FROM softwares ORDER BY nome"
        ).fetchall()
    return jsonify([dict(r) for r in rows])


@softwares_bp.post("")
def criar():
    """Cria um novo software."""
    data = request.get_json(silent=True) or {}
    nome = (data.get("nome") or "").strip()
    if not nome:
        return jsonify({"erro": "Campo 'nome' é obrigatório."}), 400

    git_url    = data.get("git_url") or None
    tecnologia = data.get("tecnologia") or None

    with get_db() as conn:
        try:
            cur = conn.execute(
                "INSERT INTO softwares (nome, git_url, tecnologia) VALUES (?, ?, ?)",
                (nome, git_url, tecnologia)
            )
            row = conn.execute(
                "SELECT id, nome, git_url, tecnologia, criado_em FROM softwares WHERE id = ?",
                (cur.lastrowid,)
            ).fetchone()
        except Exception as e:
            if "UNIQUE" in str(e):
                return jsonify({"erro": f"Software '{nome}' já existe."}), 409
            return jsonify({"erro": str(e)}), 500
    return jsonify(dict(row)), 201


@softwares_bp.delete("/<int:sid>")
def deletar(sid):
    """Remove um software e suas instâncias associadas (CASCADE)."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM softwares WHERE id = ?", (sid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Software não encontrado."}), 404
    return "", 204
