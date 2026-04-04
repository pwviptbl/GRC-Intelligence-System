"""
Blueprint do chat IA — recebe mensagem e retorna resposta do Gemini.
"""
from flask import Blueprint, jsonify, request
from app.services.gemini_service import processar_mensagem

chat_bp = Blueprint("chat", __name__, url_prefix="/api/chat")


@chat_bp.post("")
def chat():
    """
    Endpoint principal do chat.
    Corpo esperado: { "mensagem": "..." }
    """
    data = request.get_json(silent=True) or {}
    mensagem = (data.get("mensagem") or "").strip()
    if not mensagem:
        return jsonify({"erro": "Campo 'mensagem' é obrigatório."}), 400

    resultado = processar_mensagem(mensagem)
    return jsonify({
        "resposta": resultado["resposta"],
        "tipo":     resultado.get("tipo", "geral")
    })
