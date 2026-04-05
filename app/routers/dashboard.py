"""
Blueprint de dashboard — endpoints de agregação para o painel principal.
"""
from flask import Blueprint, jsonify
from app.database import get_db

dashboard_bp = Blueprint("dashboard", __name__, url_prefix="/api/dashboard")


@dashboard_bp.get("")
def resumo():
    """Retorna todos os dados agregados do dashboard."""
    with get_db() as conn:
        # Ativos
        total_clientes   = conn.execute("SELECT COUNT(*) FROM clientes").fetchone()[0]
        total_softwares  = conn.execute("SELECT COUNT(*) FROM softwares").fetchone()[0]
        total_instancias = conn.execute("SELECT COUNT(*) FROM instancias_cliente").fetchone()[0]

        # Governança
        total_politicas     = conn.execute("SELECT COUNT(*) FROM politicas").fetchone()[0]
        politicas_vigentes  = conn.execute("SELECT COUNT(*) FROM politicas WHERE status='vigente'").fetchone()[0]
        total_procedimentos = conn.execute("SELECT COUNT(*) FROM procedimentos").fetchone()[0]
        proc_vigentes       = conn.execute("SELECT COUNT(*) FROM procedimentos WHERE status='vigente'").fetchone()[0]

        # Riscos
        total_riscos     = conn.execute("SELECT COUNT(*) FROM riscos").fetchone()[0]
        riscos_abertos   = conn.execute("SELECT COUNT(*) FROM riscos WHERE status != 'fechado'").fetchone()[0]
        riscos_criticos  = conn.execute("SELECT COUNT(*) FROM riscos WHERE criticidade='Critico' AND status != 'fechado'").fetchone()[0]
        riscos_altos     = conn.execute("SELECT COUNT(*) FROM riscos WHERE criticidade='Alto' AND status != 'fechado'").fetchone()[0]
        riscos_medios    = conn.execute("SELECT COUNT(*) FROM riscos WHERE criticidade='Medio' AND status != 'fechado'").fetchone()[0]
        riscos_baixos    = conn.execute("SELECT COUNT(*) FROM riscos WHERE criticidade='Baixo' AND status != 'fechado'").fetchone()[0]

        # Incidentes
        total_incidentes    = conn.execute("SELECT COUNT(*) FROM incidentes").fetchone()[0]
        incidentes_abertos  = conn.execute("SELECT COUNT(*) FROM incidentes WHERE status NOT IN ('resolvido','fechado')").fetchone()[0]

        # Plano de Ação
        total_acoes       = conn.execute("SELECT COUNT(*) FROM plano_acoes").fetchone()[0]
        acoes_pendentes   = conn.execute("SELECT COUNT(*) FROM plano_acoes WHERE status='pendente'").fetchone()[0]
        acoes_andamento   = conn.execute("SELECT COUNT(*) FROM plano_acoes WHERE status='em_andamento'").fetchone()[0]
        acoes_concluidas  = conn.execute("SELECT COUNT(*) FROM plano_acoes WHERE status='concluida'").fetchone()[0]

        # LGPD
        lgpd_total   = conn.execute("SELECT COUNT(*) FROM lgpd_itens").fetchone()[0]
        lgpd_conf    = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='conforme'").fetchone()[0]
        lgpd_parcial = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='parcial'").fetchone()[0]
        lgpd_nconf   = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='nao_conforme'").fetchone()[0]
        lgpd_naval   = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='nao_avaliado'").fetchone()[0]

        # Últimos riscos
        ultimos_riscos = conn.execute(
            "SELECT id, titulo, criticidade, status, criado_em FROM riscos ORDER BY criado_em DESC LIMIT 5"
        ).fetchall()

        # Últimos incidentes
        ultimos_incidentes = conn.execute(
            "SELECT id, titulo, severidade, status, criado_em FROM incidentes ORDER BY criado_em DESC LIMIT 5"
        ).fetchall()

    lgpd_pct = round((lgpd_conf / lgpd_total) * 100) if lgpd_total > 0 else 0

    return jsonify({
        "ativos": {
            "clientes": total_clientes,
            "softwares": total_softwares,
            "instancias": total_instancias
        },
        "governanca": {
            "politicas": total_politicas,
            "politicas_vigentes": politicas_vigentes,
            "procedimentos": total_procedimentos,
            "procedimentos_vigentes": proc_vigentes
        },
        "riscos": {
            "total": total_riscos,
            "abertos": riscos_abertos,
            "criticos": riscos_criticos,
            "altos": riscos_altos,
            "medios": riscos_medios,
            "baixos": riscos_baixos
        },
        "incidentes": {
            "total": total_incidentes,
            "abertos": incidentes_abertos
        },
        "plano_acoes": {
            "total": total_acoes,
            "pendentes": acoes_pendentes,
            "em_andamento": acoes_andamento,
            "concluidas": acoes_concluidas
        },
        "lgpd": {
            "total": lgpd_total,
            "conforme": lgpd_conf,
            "parcial": lgpd_parcial,
            "nao_conforme": lgpd_nconf,
            "nao_avaliado": lgpd_naval,
            "percentual": lgpd_pct
        },
        "ultimos_riscos": [dict(r) for r in ultimos_riscos],
        "ultimos_incidentes": [dict(r) for r in ultimos_incidentes]
    })


@dashboard_bp.get("/maturidade")
def maturidade():
    """Calcula o score de maturidade GRC."""
    with get_db() as conn:
        # Políticas (peso 20)
        pol_total = conn.execute("SELECT COUNT(*) FROM politicas").fetchone()[0]
        pol_vigentes = conn.execute("SELECT COUNT(*) FROM politicas WHERE status='vigente'").fetchone()[0]
        pol_min_recomendadas = 5
        score_pol = min((pol_vigentes / pol_min_recomendadas) * 20, 20) if pol_min_recomendadas > 0 else 0

        # Procedimentos (peso 15)
        proc_total = conn.execute("SELECT COUNT(*) FROM procedimentos").fetchone()[0]
        proc_vigentes = conn.execute("SELECT COUNT(*) FROM procedimentos WHERE status='vigente'").fetchone()[0]
        proc_min = 3
        score_proc = min((proc_vigentes / proc_min) * 15, 15) if proc_min > 0 else 0

        # Riscos tratados (peso 20)
        riscos_total = conn.execute("SELECT COUNT(*) FROM riscos").fetchone()[0]
        riscos_fechados = conn.execute("SELECT COUNT(*) FROM riscos WHERE status='fechado'").fetchone()[0]
        riscos_criticos_ab = conn.execute("SELECT COUNT(*) FROM riscos WHERE criticidade='Critico' AND status != 'fechado'").fetchone()[0]
        score_riscos = 0
        if riscos_total > 0:
            pct_fechados = riscos_fechados / riscos_total
            penalidade_critico = min(riscos_criticos_ab * 4, 20)
            score_riscos = max((pct_fechados * 20) - penalidade_critico, 0)

        # LGPD (peso 20)
        lgpd_total = conn.execute("SELECT COUNT(*) FROM lgpd_itens").fetchone()[0]
        lgpd_conf = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='conforme'").fetchone()[0]
        lgpd_parcial = conn.execute("SELECT COUNT(*) FROM lgpd_itens WHERE conforme='parcial'").fetchone()[0]
        score_lgpd = 0
        if lgpd_total > 0:
            score_lgpd = ((lgpd_conf + lgpd_parcial * 0.5) / lgpd_total) * 20

        # Incidentes resolvidos (peso 10)
        inc_total = conn.execute("SELECT COUNT(*) FROM incidentes").fetchone()[0]
        inc_resolvidos = conn.execute("SELECT COUNT(*) FROM incidentes WHERE status IN ('resolvido','fechado')").fetchone()[0]
        score_inc = 10.0  # Se sem incidentes, score perfeito
        if inc_total > 0:
            score_inc = (inc_resolvidos / inc_total) * 10

        # Plano de Ação (peso 15)
        acoes_total = conn.execute("SELECT COUNT(*) FROM plano_acoes").fetchone()[0]
        acoes_concl = conn.execute("SELECT COUNT(*) FROM plano_acoes WHERE status='concluida'").fetchone()[0]
        score_acoes = 15.0
        if acoes_total > 0:
            score_acoes = (acoes_concl / acoes_total) * 15

        total_score = round(score_pol + score_proc + score_riscos + score_lgpd + score_inc + score_acoes)

        # Determina nível
        if total_score >= 85:
            nivel = "Nível 5 - Otimizado"
            cor = "#00ff9f"
        elif total_score >= 70:
            nivel = "Nível 4 - Gerenciado"
            cor = "#00e5ff"
        elif total_score >= 50:
            nivel = "Nível 3 - Definido"
            cor = "#ffd740"
        elif total_score >= 30:
            nivel = "Nível 2 - Inicial"
            cor = "#ff9632"
        else:
            nivel = "Nível 1 - Ad Hoc"
            cor = "#ff5370"

    return jsonify({
        "score": total_score,
        "nivel": nivel,
        "cor": cor,
        "detalhes": {
            "politicas": {"score": round(score_pol, 1), "max": 20, "desc": f"{pol_vigentes} vigentes de {pol_min_recomendadas} recomendadas"},
            "procedimentos": {"score": round(score_proc, 1), "max": 15, "desc": f"{proc_vigentes} vigentes de {proc_min} recomendados"},
            "riscos": {"score": round(score_riscos, 1), "max": 20, "desc": f"{riscos_fechados}/{riscos_total} tratados, {riscos_criticos_ab} críticos abertos"},
            "lgpd": {"score": round(score_lgpd, 1), "max": 20, "desc": f"{lgpd_conf}/{lgpd_total} conformes"},
            "incidentes": {"score": round(score_inc, 1), "max": 10, "desc": f"{inc_resolvidos}/{inc_total} resolvidos"},
            "plano_acoes": {"score": round(score_acoes, 1), "max": 15, "desc": f"{acoes_concl}/{acoes_total} concluídas"}
        }
    })
