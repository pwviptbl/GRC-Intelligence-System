"""
Blueprint de incidentes — CRUD de incidentes e timeline de resposta.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

incidentes_bp = Blueprint("incidentes", __name__, url_prefix="/api/incidentes")

STATUS_VALIDOS = {"aberto", "investigando", "contido", "resolvido", "fechado"}


@incidentes_bp.get("")
def listar():
    """Lista todos os incidentes ordenados por severidade e data."""
    with get_db() as conn:
        rows = conn.execute("""
            SELECT * FROM incidentes
            ORDER BY CASE severidade
                WHEN 'Critica' THEN 1 WHEN 'Alta' THEN 2
                WHEN 'Media' THEN 3 WHEN 'Baixa' THEN 4 ELSE 5 END,
            criado_em DESC
        """).fetchall()
    return jsonify([dict(r) for r in rows])


@incidentes_bp.get("/<int:iid>")
def obter(iid):
    """Retorna um incidente completo com timeline."""
    with get_db() as conn:
        inc = conn.execute("SELECT * FROM incidentes WHERE id = ?", (iid,)).fetchone()
        if not inc:
            return jsonify({"erro": "Incidente não encontrado."}), 404
        timeline = conn.execute(
            "SELECT * FROM incidente_timeline WHERE incidente_id = ? ORDER BY criado_em",
            (iid,)
        ).fetchall()
    resultado = dict(inc)
    resultado["timeline"] = [dict(t) for t in timeline]
    return jsonify(resultado)


@incidentes_bp.post("")
def criar():
    """Cria um novo incidente."""
    data = request.get_json(silent=True) or {}
    titulo = (data.get("titulo") or "").strip()
    if not titulo:
        return jsonify({"erro": "Campo 'titulo' é obrigatório."}), 400

    with get_db() as conn:
        cur = conn.execute(
            """INSERT INTO incidentes (titulo, descricao, severidade, status, detectado_por,
               data_deteccao, risco_vinculado, licoes_aprendidas)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
            (titulo, data.get("descricao", ""), data.get("severidade", "Media"),
             data.get("status", "aberto"), data.get("detectado_por", ""),
             data.get("data_deteccao", ""), data.get("risco_vinculado", ""),
             data.get("licoes_aprendidas", ""))
        )
        row = conn.execute("SELECT * FROM incidentes WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201


@incidentes_bp.put("/<int:iid>")
def atualizar(iid):
    """Atualiza um incidente."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM incidentes WHERE id = ?", (iid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Incidente não encontrado."}), 404
        conn.execute(
            """UPDATE incidentes SET titulo=?, descricao=?, severidade=?, status=?,
               detectado_por=?, data_deteccao=?, risco_vinculado=?, licoes_aprendidas=?,
               atualizado_em=CURRENT_TIMESTAMP WHERE id=?""",
            (data.get("titulo", atual["titulo"]), data.get("descricao", atual["descricao"]),
             data.get("severidade", atual["severidade"]), data.get("status", atual["status"]),
             data.get("detectado_por", atual["detectado_por"]),
             data.get("data_deteccao", atual["data_deteccao"]),
             data.get("risco_vinculado", atual["risco_vinculado"]),
             data.get("licoes_aprendidas", atual["licoes_aprendidas"]), iid)
        )
        row = conn.execute("SELECT * FROM incidentes WHERE id = ?", (iid,)).fetchone()
        timeline = conn.execute(
            "SELECT * FROM incidente_timeline WHERE incidente_id = ? ORDER BY criado_em", (iid,)
        ).fetchall()
    resultado = dict(row)
    resultado["timeline"] = [dict(t) for t in timeline]
    return jsonify(resultado)


@incidentes_bp.delete("/<int:iid>")
def deletar(iid):
    """Remove um incidente."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM incidentes WHERE id = ?", (iid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Incidente não encontrado."}), 404
    return "", 204


@incidentes_bp.post("/<int:iid>/timeline")
def adicionar_timeline(iid):
    """Adiciona uma ação na timeline do incidente."""
    data = request.get_json(silent=True) or {}
    acao = (data.get("acao") or "").strip()
    if not acao:
        return jsonify({"erro": "Campo 'acao' é obrigatório."}), 400
    with get_db() as conn:
        if not conn.execute("SELECT 1 FROM incidentes WHERE id = ?", (iid,)).fetchone():
            return jsonify({"erro": "Incidente não encontrado."}), 404
        cur = conn.execute(
            "INSERT INTO incidente_timeline (incidente_id, acao, responsavel) VALUES (?, ?, ?)",
            (iid, acao, data.get("responsavel", ""))
        )
        conn.execute("UPDATE incidentes SET atualizado_em=CURRENT_TIMESTAMP WHERE id=?", (iid,))
        row = conn.execute("SELECT * FROM incidente_timeline WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201
