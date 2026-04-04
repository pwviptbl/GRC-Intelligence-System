"""
Blueprint de procedimentos — CRUD das tabelas 'procedimentos' e 'procedimento_etapas'.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

procedimentos_bp = Blueprint("procedimentos", __name__, url_prefix="/api/procedimentos")

CAMPOS_STATUS = {"rascunho", "vigente", "em_revisao", "obsoleta"}

SQL_LISTAR = """
    SELECT id, titulo, tipo, status, criado_em, atualizado_em
    FROM procedimentos ORDER BY tipo, titulo
"""


@procedimentos_bp.get("")
def listar():
    """Lista todos os procedimentos com seus metadados."""
    with get_db() as conn:
        rows = conn.execute(SQL_LISTAR).fetchall()
    return jsonify([dict(r) for r in rows])


@procedimentos_bp.get("/<int:pid>")
def obter(pid):
    """Retorna um procedimento completo com todas as suas etapas."""
    with get_db() as conn:
        proc = conn.execute(
            "SELECT * FROM procedimentos WHERE id = ?", (pid,)
        ).fetchone()
        if not proc:
            return jsonify({"erro": "Procedimento não encontrado."}), 404

        etapas = conn.execute(
            "SELECT * FROM procedimento_etapas WHERE procedimento_id = ? ORDER BY ordem",
            (pid,)
        ).fetchall()

    resultado = dict(proc)
    resultado["etapas"] = [dict(e) for e in etapas]
    return jsonify(resultado)


@procedimentos_bp.post("")
def criar():
    """Cria um novo procedimento com suas etapas."""
    data   = request.get_json(silent=True) or {}
    titulo = (data.get("titulo") or "").strip()
    tipo   = (data.get("tipo") or "").strip()
    status = (data.get("status") or "rascunho").strip()
    etapas = data.get("etapas", [])  # Lista de dicts com nome_etapa, responsavel, descricao, sla

    if not titulo or not tipo:
        return jsonify({"erro": "Campos 'titulo' e 'tipo' são obrigatórios."}), 400
    if status not in CAMPOS_STATUS:
        status = "rascunho"

    with get_db() as conn:
        cur = conn.execute(
            "INSERT INTO procedimentos (titulo, tipo, status) VALUES (?, ?, ?)",
            (titulo, tipo, status)
        )
        proc_id = cur.lastrowid

        # Insere as etapas se fornecidas
        for i, etapa in enumerate(etapas, start=1):
            conn.execute(
                """INSERT INTO procedimento_etapas
                   (procedimento_id, ordem, nome_etapa, responsavel, descricao, sla)
                   VALUES (?, ?, ?, ?, ?, ?)""",
                (
                    proc_id,
                    i,
                    etapa.get("nome_etapa", "").strip(),
                    etapa.get("responsavel", "").strip(),
                    etapa.get("descricao", "").strip(),
                    etapa.get("sla", "").strip()
                )
            )

        proc = conn.execute("SELECT * FROM procedimentos WHERE id = ?", (proc_id,)).fetchone()
        etapas_salvas = conn.execute(
            "SELECT * FROM procedimento_etapas WHERE procedimento_id = ? ORDER BY ordem",
            (proc_id,)
        ).fetchall()

    resultado = dict(proc)
    resultado["etapas"] = [dict(e) for e in etapas_salvas]
    return jsonify(resultado), 201


@procedimentos_bp.put("/<int:pid>")
def atualizar(pid):
    """Atualiza metadados e reescreve as etapas de um procedimento."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM procedimentos WHERE id = ?", (pid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Procedimento não encontrado."}), 404

        titulo = (data.get("titulo") or atual["titulo"]).strip()
        tipo   = (data.get("tipo") or atual["tipo"]).strip()
        status = (data.get("status") or atual["status"]).strip()
        etapas = data.get("etapas")  # Se não enviado, não altera etapas

        if status not in CAMPOS_STATUS:
            status = atual["status"]

        conn.execute(
            """UPDATE procedimentos
               SET titulo=?, tipo=?, status=?, atualizado_em=CURRENT_TIMESTAMP
               WHERE id=?""",
            (titulo, tipo, status, pid)
        )

        # Reescreve as etapas se fornecidas
        if etapas is not None:
            conn.execute("DELETE FROM procedimento_etapas WHERE procedimento_id = ?", (pid,))
            for i, etapa in enumerate(etapas, start=1):
                conn.execute(
                    """INSERT INTO procedimento_etapas
                       (procedimento_id, ordem, nome_etapa, responsavel, descricao, sla)
                       VALUES (?, ?, ?, ?, ?, ?)""",
                    (
                        pid, i,
                        etapa.get("nome_etapa", "").strip(),
                        etapa.get("responsavel", "").strip(),
                        etapa.get("descricao", "").strip(),
                        etapa.get("sla", "").strip()
                    )
                )

        proc = conn.execute("SELECT * FROM procedimentos WHERE id = ?", (pid,)).fetchone()
        etapas_salvas = conn.execute(
            "SELECT * FROM procedimento_etapas WHERE procedimento_id = ? ORDER BY ordem",
            (pid,)
        ).fetchall()

    resultado = dict(proc)
    resultado["etapas"] = [dict(e) for e in etapas_salvas]
    return jsonify(resultado)


@procedimentos_bp.delete("/<int:pid>")
def deletar(pid):
    """Remove um procedimento e suas etapas (CASCADE)."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM procedimentos WHERE id = ?", (pid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Procedimento não encontrado."}), 404
    return "", 204
