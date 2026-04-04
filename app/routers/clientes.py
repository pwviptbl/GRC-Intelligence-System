"""
Blueprint de clientes — CRUD da tabela 'clientes'.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

clientes_bp = Blueprint("clientes", __name__, url_prefix="/api/clientes")


@clientes_bp.get("")
def listar():
    """Lista todos os clientes em ordem alfabética."""
    with get_db() as conn:
        rows = conn.execute(
            "SELECT id, nome, criado_em FROM clientes ORDER BY nome"
        ).fetchall()
    return jsonify([dict(r) for r in rows])


@clientes_bp.post("")
def criar():
    """Cria um novo cliente."""
    data = request.get_json(silent=True) or {}
    nome = (data.get("nome") or "").strip()
    if not nome:
        return jsonify({"erro": "Campo 'nome' é obrigatório."}), 400

    with get_db() as conn:
        try:
            cur = conn.execute("INSERT INTO clientes (nome) VALUES (?)", (nome,))
            row = conn.execute(
                "SELECT id, nome, criado_em FROM clientes WHERE id = ?",
                (cur.lastrowid,)
            ).fetchone()
        except Exception as e:
            if "UNIQUE" in str(e):
                return jsonify({"erro": f"Cliente '{nome}' já existe."}), 409
            return jsonify({"erro": str(e)}), 500
    return jsonify(dict(row)), 201


@clientes_bp.delete("/<int:cid>")
def deletar(cid):
    """Remove um cliente e suas instâncias associadas (CASCADE)."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM clientes WHERE id = ?", (cid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Cliente não encontrado."}), 404
    return "", 204
