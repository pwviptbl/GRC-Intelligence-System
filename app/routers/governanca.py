"""
Blueprint de governança — endpoints de IA para políticas, procedimentos e análise de lacunas.
"""
from flask import Blueprint, jsonify, request
from app.database import get_db
from app.services import gemini_service

governanca_bp = Blueprint("governanca", __name__, url_prefix="/api/governanca")


@governanca_bp.post("/politica/gerar")
def gerar_politica():
    """
    Recebe categoria + respostas do usuário e pede ao Gemini para gerar
    o documento completo de uma política em Markdown.
    """
    data      = request.get_json(silent=True) or {}
    categoria = data.get("categoria", "")
    respostas = data.get("respostas", "")  # Texto livre com as respostas

    prompt = (
        f"Gere um documento completo de política corporativa para a categoria: **{categoria}**.\n"
        f"Informações fornecidas pela empresa:\n{respostas}\n\n"
        "O documento deve conter: Objetivo, Escopo, Responsabilidades, Diretrizes, "
        "Penalidades por não conformidade e Revisão. "
        "Retorne APENAS o documento em Markdown, sem JSON."
    )

    resultado = gemini_service.processar_governanca(prompt)
    return jsonify({"conteudo": resultado})


@governanca_bp.post("/politica/perguntas")
def perguntas_politica():
    """
    Recebe uma categoria e retorna todas as perguntas necessárias
    para criar um documento de política completo.
    """
    data      = request.get_json(silent=True) or {}
    categoria = data.get("categoria", "")

    prompt = (
        f"Liste as perguntas necessárias para criar uma política completa de: **{categoria}**.\n"
        "Retorne APENAS um array JSON com as perguntas, sem mais nada:\n"
        '["Pergunta 1?", "Pergunta 2?", ...]'
    )

    resultado = gemini_service.processar_governanca_json(prompt)
    return jsonify({"perguntas": resultado})


@governanca_bp.post("/procedimento/gerar")
def gerar_procedimento():
    """
    Recebe o tipo de procedimento e retorna as etapas sugeridas pela IA.
    """
    data = request.get_json(silent=True) or {}
    tipo = data.get("tipo", "")

    prompt = (
        f"Gere as etapas padrão para o procedimento operacional: **{tipo}**.\n"
        "Retorne APENAS um array JSON com objetos no formato:\n"
        '[{"nome_etapa": "...", "responsavel": "", "descricao": "Descrição detalhada do que fazer nesta etapa", "sla": ""}]\n'
        "O campo responsavel e sla devem ficar em branco para o usuário preencher. "
        "A descricao deve ser detalhada com boas práticas."
    )

    resultado = gemini_service.processar_governanca_json(prompt)
    return jsonify({"etapas": resultado})


@governanca_bp.get("/lacunas")
def analisar_lacunas():
    """
    Lê todas as políticas e procedimentos cadastrados e pede ao Gemini
    para identificar lacunas em relação às boas práticas de GRC.
    """
    with get_db() as conn:
        politicas = conn.execute(
            "SELECT titulo, categoria, status FROM politicas"
        ).fetchall()
        procedimentos = conn.execute(
            "SELECT titulo, tipo, status FROM procedimentos"
        ).fetchall()

    lista_politicas = "\n".join(
        f"- [{p['status'].upper()}] {p['titulo']} ({p['categoria']})"
        for p in politicas
    ) or "Nenhuma política cadastrada."

    lista_procedimentos = "\n".join(
        f"- [{p['status'].upper()}] {p['titulo']} ({p['tipo']})"
        for p in procedimentos
    ) or "Nenhum procedimento cadastrado."

    prompt = (
        "Você é um especialista em GRC (Governança, Risco e Conformidade) para empresas de tecnologia.\n"
        "A empresa DBSeller é uma software house que fornece sistemas para municípios e entidades públicas, "
        "portanto trata dados de cidadãos e servidores públicos (relevante para LGPD).\n\n"
        "## Documentos já existentes:\n"
        f"### Políticas:\n{lista_politicas}\n\n"
        f"### Procedimentos:\n{lista_procedimentos}\n\n"
        "## Sua tarefa:\n"
        "Analise o que existe e retorne um JSON com exatamente esta estrutura:\n"
        "{\n"
        '  "coberto": [{"titulo": "...", "tipo": "politica|procedimento", "observacao": "..."}],\n'
        '  "incompleto": [{"titulo": "...", "tipo": "politica|procedimento", "observacao": "..."}],\n'
        '  "ausente": [{"titulo": "...", "tipo": "politica|procedimento", "risco": "...", "categoria": "...", "prioridade": "alta|media|baixa"}]\n'
        "}\n"
        "Retorne APENAS o JSON, sem explicações adicionais."
    )

    resultado = gemini_service.processar_governanca_json(prompt)
    return jsonify(resultado)
