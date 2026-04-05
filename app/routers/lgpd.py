"""
Blueprint de conformidade LGPD — checklist de artigos e avaliação.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

lgpd_bp = Blueprint("lgpd", __name__, url_prefix="/api/lgpd")


@lgpd_bp.get("")
def listar():
    """Lista todos os itens de conformidade LGPD."""
    with get_db() as conn:
        rows = conn.execute(
            "SELECT * FROM lgpd_itens ORDER BY categoria, artigo"
        ).fetchall()
    return jsonify([dict(r) for r in rows])


@lgpd_bp.put("/<int:item_id>")
def atualizar(item_id):
    """Atualiza o status de conformidade e observações de um item LGPD."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM lgpd_itens WHERE id = ?", (item_id,)).fetchone()
        if not atual:
            return jsonify({"erro": "Item não encontrado."}), 404
        conn.execute(
            """UPDATE lgpd_itens SET conforme=?, observacao=?, evidencia=?,
               atualizado_em=CURRENT_TIMESTAMP WHERE id=?""",
            (data.get("conforme", atual["conforme"]),
             data.get("observacao", atual["observacao"]),
             data.get("evidencia", atual["evidencia"]), item_id)
        )
        row = conn.execute("SELECT * FROM lgpd_itens WHERE id = ?", (item_id,)).fetchone()
    return jsonify(dict(row))


@lgpd_bp.get("/resumo")
def resumo():
    """Retorna resumo de conformidade LGPD."""
    with get_db() as conn:
        total    = conn.execute("SELECT COUNT(*) FROM lgpd_itens").fetchone()[0]
        conforme = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='conforme'").fetchone()[0]
        parcial  = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='parcial'").fetchone()[0]
        nao_conf = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='nao_conforme'").fetchone()[0]
        nao_aval = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='nao_avaliado'").fetchone()[0]
    pct = round((conforme / total) * 100) if total > 0 else 0
    return jsonify({
        "total": total, "conforme": conforme, "parcial": parcial,
        "nao_conforme": nao_conf, "nao_avaliado": nao_aval,
        "percentual_conforme": pct
    })
