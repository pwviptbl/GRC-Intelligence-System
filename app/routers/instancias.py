"""
Blueprint de instâncias — CRUD da tabela 'instancias_cliente' com JOINs.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

instancias_bp = Blueprint("instancias", __name__, url_prefix="/api/instancias")

SQL_JOIN = """
    SELECT
        ic.id,
        ic.cliente_id,
        ic.software_id,
        c.nome  AS cliente_nome,
        s.nome  AS software_nome,
        ic.git_custom_url,
        ic.branch,
        ic.criado_em
    FROM instancias_cliente ic
    JOIN clientes  c ON c.id = ic.cliente_id
    JOIN softwares s ON s.id = ic.software_id
"""


@instancias_bp.get("")
def listar():
    """Lista todas as instâncias com nomes de cliente e software."""
    with get_db() as conn:
        rows = conn.execute(SQL_JOIN + " ORDER BY c.nome, s.nome").fetchall()
    return jsonify([dict(r) for r in rows])


@instancias_bp.get("/cliente/<int:cliente_id>")
def listar_por_cliente(cliente_id):
    """Lista todas as instâncias de um determinado cliente_id."""
    with get_db() as conn:
        rows = conn.execute(SQL_JOIN + " WHERE ic.cliente_id = ? ORDER BY s.nome", (cliente_id,)).fetchall()
    return jsonify([dict(r) for r in rows])


@instancias_bp.post("")
def criar():
    """Cria uma nova instância vinculando cliente a um software."""
    data = request.get_json(silent=True) or {}
    cliente_id  = data.get("cliente_id")
    software_id = data.get("software_id")
    branch      = (data.get("branch") or "master").strip()
    git_custom  = data.get("git_custom_url") or None

    if not cliente_id or not software_id:
        return jsonify({"erro": "Campos 'cliente_id' e 'software_id' são obrigatórios."}), 400

    with get_db() as conn:
        # Valida existência do cliente e software
        if not conn.execute("SELECT 1 FROM clientes WHERE id=?", (cliente_id,)).fetchone():
            return jsonify({"erro": "Cliente não encontrado."}), 404
        if not conn.execute("SELECT 1 FROM softwares WHERE id=?", (software_id,)).fetchone():
            return jsonify({"erro": "Software não encontrado."}), 404

        cur = conn.execute(
            "INSERT INTO instancias_cliente (cliente_id, software_id, git_custom_url, branch) VALUES (?,?,?,?)",
            (cliente_id, software_id, git_custom, branch)
        )
        row = conn.execute(
            SQL_JOIN + " WHERE ic.id = ?", (cur.lastrowid,)
        ).fetchone()
    return jsonify(dict(row)), 201


@instancias_bp.delete("/<int:iid>")
def deletar(iid):
    """Remove uma instância."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM instancias_cliente WHERE id=?", (iid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Instância não encontrada."}), 404
    return "", 204
