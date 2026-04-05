"""
Blueprint de riscos — CRUD de riscos e histórico de ações.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db

riscos_bp = Blueprint("riscos", __name__, url_prefix="/api/riscos")

STATUS_VALIDOS       = {"aberto", "em_tratamento", "monitorando", "fechado"}
PROBABILIDADE_VALIDA = {"Alta", "Media", "Baixa"}
IMPACTO_VALIDO       = {"Alto", "Medio", "Baixo"}


def _calcular_criticidade(probabilidade: str, impacto: str) -> str:
    """Calcula criticidade com base na matriz probabilidade × impacto."""
    matriz = {
        ("Alta",  "Alto"):  "Critico",
        ("Alta",  "Medio"): "Alto",
        ("Alta",  "Baixo"): "Medio",
        ("Media", "Alto"):  "Alto",
        ("Media", "Medio"): "Medio",
        ("Media", "Baixo"): "Baixo",
        ("Baixa", "Alto"):  "Medio",
        ("Baixa", "Medio"): "Baixo",
        ("Baixa", "Baixo"): "Baixo",
    }
    return matriz.get((probabilidade, impacto), "Medio")


@riscos_bp.get("")
def listar():
    """Lista todos os riscos ordenados por criticidade e data."""
    with get_db() as conn:
        rows = conn.execute("""
            SELECT id, titulo, origem, ativo_afetado, probabilidade, impacto,
                   criticidade, status, responsavel, criado_em, atualizado_em
            FROM riscos
            ORDER BY CASE criticidade
                WHEN 'Critico' THEN 1 WHEN 'Alto' THEN 2
                WHEN 'Medio'   THEN 3 WHEN 'Baixo' THEN 4 ELSE 5 END,
            criado_em DESC
        """).fetchall()
    return jsonify([dict(r) for r in rows])


@riscos_bp.get("/<int:rid>")
def obter(rid):
    """Retorna um risco completo com histórico de ações."""
    with get_db() as conn:
        risco = conn.execute(
            "SELECT * FROM riscos WHERE id = ?", (rid,)
        ).fetchone()
        if not risco:
            return jsonify({"erro": "Risco não encontrado."}), 404

        historico = conn.execute(
            "SELECT * FROM risco_historico WHERE risco_id = ? ORDER BY criado_em DESC",
            (rid,)
        ).fetchall()

    resultado = dict(risco)
    resultado["historico"] = [dict(h) for h in historico]
    return jsonify(resultado)


@riscos_bp.post("")
def criar():
    """Cria um novo risco calculando automaticamente a criticidade."""
    data = request.get_json(silent=True) or {}

    titulo           = (data.get("titulo") or "").strip()
    descricao        = data.get("descricao", "")
    origem           = (data.get("origem") or "Técnico").strip()
    ativo_afetado    = data.get("ativo_afetado", "")
    probabilidade    = (data.get("probabilidade") or "Media").strip()
    impacto          = (data.get("impacto") or "Medio").strip()
    status           = (data.get("status") or "aberto").strip()
    politica_ref     = data.get("politica_ref", "")
    procedimento_ref = data.get("procedimento_ref", "")
    plano_acao       = data.get("plano_acao", "")
    responsavel      = data.get("responsavel", "")

    if not titulo:
        return jsonify({"erro": "Campo 'titulo' é obrigatório."}), 400
    if status not in STATUS_VALIDOS:
        status = "aberto"

    criticidade = _calcular_criticidade(probabilidade, impacto)

    with get_db() as conn:
        cur = conn.execute(
            """INSERT INTO riscos
               (titulo, descricao, origem, ativo_afetado, probabilidade, impacto,
                criticidade, status, politica_ref, procedimento_ref, plano_acao, responsavel)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
            (titulo, descricao, origem, ativo_afetado, probabilidade, impacto,
             criticidade, status, politica_ref, procedimento_ref, plano_acao, responsavel)
        )
        row = conn.execute("SELECT * FROM riscos WHERE id = ?", (cur.lastrowid,)).fetchone()
    return jsonify(dict(row)), 201


@riscos_bp.put("/<int:rid>")
def atualizar(rid):
    """Atualiza um risco e recalcula a criticidade."""
    data = request.get_json(silent=True) or {}
    with get_db() as conn:
        atual = conn.execute("SELECT * FROM riscos WHERE id = ?", (rid,)).fetchone()
        if not atual:
            return jsonify({"erro": "Risco não encontrado."}), 404

        titulo           = (data.get("titulo")        or atual["titulo"]).strip()
        descricao        = data.get("descricao",           atual["descricao"])
        origem           = (data.get("origem")         or atual["origem"]).strip()
        ativo_afetado    = data.get("ativo_afetado",       atual["ativo_afetado"])
        probabilidade    = (data.get("probabilidade")  or atual["probabilidade"]).strip()
        impacto          = (data.get("impacto")        or atual["impacto"]).strip()
        status           = (data.get("status")         or atual["status"]).strip()
        politica_ref     = data.get("politica_ref",        atual["politica_ref"])
        procedimento_ref = data.get("procedimento_ref",    atual["procedimento_ref"])
        plano_acao       = data.get("plano_acao",          atual["plano_acao"])
        responsavel      = data.get("responsavel",         atual["responsavel"])

        if status not in STATUS_VALIDOS:
            status = atual["status"]

        criticidade = _calcular_criticidade(probabilidade, impacto)

        conn.execute(
            """UPDATE riscos
               SET titulo=?, descricao=?, origem=?, ativo_afetado=?, probabilidade=?,
                   impacto=?, criticidade=?, status=?, politica_ref=?, procedimento_ref=?,
                   plano_acao=?, responsavel=?, atualizado_em=CURRENT_TIMESTAMP
               WHERE id=?""",
            (titulo, descricao, origem, ativo_afetado, probabilidade, impacto,
             criticidade, status, politica_ref, procedimento_ref, plano_acao, responsavel, rid)
        )
        row = conn.execute("SELECT * FROM riscos WHERE id = ?", (rid,)).fetchone()
        historico = conn.execute(
            "SELECT * FROM risco_historico WHERE risco_id = ? ORDER BY criado_em DESC", (rid,)
        ).fetchall()

    resultado = dict(row)
    resultado["historico"] = [dict(h) for h in historico]
    return jsonify(resultado)


@riscos_bp.delete("/<int:rid>")
def deletar(rid):
    """Remove um risco e seu histórico (CASCADE)."""
    with get_db() as conn:
        af = conn.execute("DELETE FROM riscos WHERE id = ?", (rid,)).rowcount
    if af == 0:
        return jsonify({"erro": "Risco não encontrado."}), 404
    return "", 204


# ── Histórico de Ações ────────────────────────────────────────────────────────

@riscos_bp.post("/<int:rid>/historico")
def adicionar_historico(rid):
    """Adiciona uma entrada no histórico de ações de um risco."""
    data = request.get_json(silent=True) or {}
    comentario = (data.get("comentario") or "").strip()
    autor      = (data.get("autor") or "Analista").strip()

    if not comentario:
        return jsonify({"erro": "Campo 'comentario' é obrigatório."}), 400

    with get_db() as conn:
        if not conn.execute("SELECT 1 FROM riscos WHERE id = ?", (rid,)).fetchone():
            return jsonify({"erro": "Risco não encontrado."}), 404
        cur = conn.execute(
            "INSERT INTO risco_historico (risco_id, comentario, autor) VALUES (?, ?, ?)",
            (rid, comentario, autor)
        )
        # Atualiza timestamp do risco
        conn.execute(
            "UPDATE riscos SET atualizado_em=CURRENT_TIMESTAMP WHERE id=?", (rid,)
        )
        row = conn.execute(
            "SELECT * FROM risco_historico WHERE id = ?", (cur.lastrowid,)
        ).fetchone()
    return jsonify(dict(row)), 201
