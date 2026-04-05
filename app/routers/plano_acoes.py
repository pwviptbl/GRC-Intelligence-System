"""
Blueprint de plano de ação — CRUD para ações consolidadas de todas as origens.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

plano_acoes_bp = Blueprint("plano_acoes", __name__, url_prefix="/api/plano-acoes")


@plano_acoes_bp.get("")
def listar():
    """Lista todas as ações ordenadas por prioridade e status."""
    with get_db() as conn:
        rows = conn.execute("""
            SELECT * FROM plano_acoes
            ORDER BY CASE prioridade
                WHEN 'critica' THEN 1 WHEN 'alta' THEN 2
                WHEN 'media' THEN 3 WHEN 'baixa' THEN 4 ELSE 5 END,
            CASE status WHEN 'pendente' THEN 1 WHEN 'em_andamento' THEN 2
                WHEN 'concluida' THEN 3 ELSE 4 END
        """).fetchall()
    return jsonify([dict(r) for r in rows])


@plano_acoes_bp.post("")
def criar():
    """Cria uma nova ação."""
    data = request.get_json(silent=True) or {}
    titulo = (data.get("titulo") or "").strip()
    if not titulo:
        return jsonify({"erro": "Campo 'titulo' é obrigatório."}), 400
    with get_db() as conn:
        cur = conn.execute(
            """INSERT INTO plano_acoes (titulo, descricao, origem, origem_id, responsavel, prioridade, status)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (titulo, data.get("descricao", ""), data.get("origem", ""),
             data.get("origem_id"), data.get("responsavel", ""),
             data.get("prioridade", "media"), data.get("status", "pendente"))
        )
        row = conn.execute("SELECT * FROM plano_acoes WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201


@plano_acoes_bp.put("/<int:aid>")
def atualizar(aid):
    """Atualiza uma ação."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM plano_acoes WHERE id = ?", (aid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Ação não encontrada."}), 404
        conn.execute(
            """UPDATE plano_acoes SET titulo=?, descricao=?, origem=?, origem_id=?,
               responsavel=?, prioridade=?, status=?, atualizado_em=CURRENT_TIMESTAMP WHERE id=?""",
            (data.get("titulo", atual["titulo"]), data.get("descricao", atual["descricao"]),
             data.get("origem", atual["origem"]), data.get("origem_id", atual["origem_id"]),
             data.get("responsavel", atual["responsavel"]),
             data.get("prioridade", atual["prioridade"]),
             data.get("status", atual["status"]), aid)
        )
        row = conn.execute("SELECT * FROM plano_acoes WHERE id = ?", (aid,)).fetchone()
    return jsonify(dict(row))


@plano_acoes_bp.delete("/<int:aid>")
def deletar(aid):
    """Remove uma ação."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM plano_acoes WHERE id = ?", (aid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Ação não encontrada."}), 404
    return "", 204
