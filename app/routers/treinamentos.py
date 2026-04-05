"""
Blueprint de treinamentos — CRUD de treinamentos e registro de participação.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

treinamentos_bp = Blueprint("treinamentos", __name__, url_prefix="/api/treinamentos")


@treinamentos_bp.get("")
def listar():
    """Lista todos os treinamentos com contagem de participantes."""
    with get_db() as conn:
        rows = conn.execute("""
            SELECT t.*, 
                   COUNT(r.id) as total_registros,
                   SUM(CASE WHEN r.status = 'concluido' THEN 1 ELSE 0 END) as concluidos
            FROM treinamentos t
            LEFT JOIN treinamento_registros r ON r.treinamento_id = t.id
            GROUP BY t.id
            ORDER BY t.criado_em DESC
        """).fetchall()
    return jsonify([dict(r) for r in rows])


@treinamentos_bp.get("/<int:tid>")
def obter(tid):
    """Retorna um treinamento com todos os registros de participação."""
    with get_db() as conn:
        treinamento = conn.execute("SELECT * FROM treinamentos WHERE id = ?", (tid,)).fetchone()
        if not treinamento:
            return jsonify({"erro": "Treinamento não encontrado."}), 404
        registros = conn.execute(
            "SELECT * FROM treinamento_registros WHERE treinamento_id = ? ORDER BY colaborador",
            (tid,)
        ).fetchall()
    resultado = dict(treinamento)
    resultado["registros"] = [dict(r) for r in registros]
    return jsonify(resultado)


@treinamentos_bp.post("")
def criar():
    """Cria um novo treinamento."""
    data = request.get_json(silent=True) or {}
    titulo = (data.get("titulo") or "").strip()
    if not titulo:
        return jsonify({"erro": "Campo 'titulo' é obrigatório."}), 400
    with get_db() as conn:
        cur = conn.execute(
            "INSERT INTO treinamentos (titulo, descricao, categoria, obrigatorio) VALUES (?, ?, ?, ?)",
            (titulo, data.get("descricao", ""), data.get("categoria", "Segurança"),
             1 if data.get("obrigatorio") else 0)
        )
        row = conn.execute("SELECT * FROM treinamentos WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201


@treinamentos_bp.put("/<int:tid>")
def atualizar(tid):
    """Atualiza um treinamento."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM treinamentos WHERE id = ?", (tid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Treinamento não encontrado."}), 404
        conn.execute(
            "UPDATE treinamentos SET titulo=?, descricao=?, categoria=?, obrigatorio=? WHERE id=?",
            (data.get("titulo", atual["titulo"]), data.get("descricao", atual["descricao"]),
             data.get("categoria", atual["categoria"]),
             1 if data.get("obrigatorio", atual["obrigatorio"]) else 0, tid)
        )
        row = conn.execute("SELECT * FROM treinamentos WHERE id = ?", (tid,)).fetchone()
    return jsonify(dict(row))


@treinamentos_bp.delete("/<int:tid>")
def deletar(tid):
    """Remove um treinamento."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM treinamentos WHERE id = ?", (tid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Treinamento não encontrado."}), 404
    return "", 204


# ── Registros de participação ─────────────────────────────────────────────────

@treinamentos_bp.post("/<int:tid>/registros")
def adicionar_registro(tid):
    """Adiciona um registro de participação."""
    data = request.get_json(silent=True) or {}
    colaborador = (data.get("colaborador") or "").strip()
    if not colaborador:
        return jsonify({"erro": "Campo 'colaborador' é obrigatório."}), 400
    with get_db() as conn:
        if not conn.execute("SELECT 1 FROM treinamentos WHERE id = ?", (tid,)).fetchone():
            return jsonify({"erro": "Treinamento não encontrado."}), 404
        cur = conn.execute(
            "INSERT INTO treinamento_registros (treinamento_id, colaborador, status, data_conclusao) VALUES (?, ?, ?, ?)",
            (tid, colaborador, data.get("status", "pendente"), data.get("data_conclusao"))
        )
        row = conn.execute("SELECT * FROM treinamento_registros WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201


@treinamentos_bp.put("/registros/<int:rid>")
def atualizar_registro(rid):
    """Atualiza status de um registro de participação."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM treinamento_registros WHERE id = ?", (rid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Registro não encontrado."}), 404
        conn.execute(
            "UPDATE treinamento_registros SET status=?, data_conclusao=? WHERE id=?",
            (data.get("status", atual["status"]),
             data.get("data_conclusao", atual["data_conclusao"]), rid)
        )
        row = conn.execute("SELECT * FROM treinamento_registros WHERE id = ?", (rid,)).fetchone()
    return jsonify(dict(row))


@treinamentos_bp.delete("/registros/<int:rid>")
def deletar_registro(rid):
    """Remove um registro de participação."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM treinamento_registros WHERE id = ?", (rid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Registro não encontrado."}), 404
    return "", 204
