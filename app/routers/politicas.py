"""
Blueprint de políticas — CRUD da tabela 'politicas'.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

politicas_bp = Blueprint("politicas", __name__, url_prefix="/api/politicas")

CAMPOS_STATUS = {"rascunho", "vigente", "em_revisao", "obsoleta"}


@politicas_bp.get("")
def listar():
    """Lista todas as políticas ordenadas por categoria e título."""
    with get_db() as conn:
        rows = conn.execute(
            "SELECT id, titulo, categoria, versao, status, criado_em, atualizado_em "
            "FROM politicas ORDER BY categoria, titulo"
        ).fetchall()
    return jsonify([dict(r) for r in rows])


@politicas_bp.get("/<int:pid>")
def obter(pid):
    """Retorna uma política completa incluindo o conteúdo."""
    with get_db() as conn:
        row = conn.execute(
            "SELECT * FROM politicas WHERE id = ?", (pid,)
        ).fetchone()
    if not row:
        return jsonify({"erro": "Política não encontrada."}), 404
    return jsonify(dict(row))


@politicas_bp.post("")
def criar():
    """Cria uma nova política."""
    data = request.get_json(silent=True) or {}
    titulo    = (data.get("titulo") or "").strip()
    categoria = (data.get("categoria") or "").strip()
    conteudo  = data.get("conteudo", "")
    versao    = (data.get("versao") or "1.0").strip()
    status    = (data.get("status") or "rascunho").strip()

    if not titulo or not categoria:
        return jsonify({"erro": "Campos 'titulo' e 'categoria' são obrigatórios."}), 400
    if status not in CAMPOS_STATUS:
        status = "rascunho"

    with get_db() as conn:
        cur = conn.execute(
            "INSERT INTO politicas (titulo, categoria, versao, status, conteudo) "
            "VALUES (?, ?, ?, ?, ?)",
            (titulo, categoria, versao, status, conteudo)
        )
        row = conn.execute("SELECT * FROM politicas WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201


@politicas_bp.put("/<int:pid>")
def atualizar(pid):
    """Atualiza os dados e conteúdo de uma política existente."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM politicas WHERE id = ?", (pid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Política não encontrada."}), 404

        titulo    = (data.get("titulo") or atual["titulo"]).strip()
        categoria = (data.get("categoria") or atual["categoria"]).strip()
        versao    = (data.get("versao") or atual["versao"]).strip()
        status    = (data.get("status") or atual["status"]).strip()
        conteudo  = data.get("conteudo", atual["conteudo"])

        if status not in CAMPOS_STATUS:
            status = atual["status"]

        conn.execute(
            """UPDATE politicas
               SET titulo=?, categoria=?, versao=?, status=?, conteudo=?,
                   atualizado_em=CURRENT_TIMESTAMP
               WHERE id=?""",
            (titulo, categoria, versao, status, conteudo, pid)
        )
        row = conn.execute("SELECT * FROM politicas WHERE id = ?", (pid,)).fetchone()
    return jsonify(dict(row))


@politicas_bp.delete("/<int:pid>")
def deletar(pid):
    """Remove uma política."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM politicas WHERE id = ?", (pid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Política não encontrada."}), 404
    return "", 204
