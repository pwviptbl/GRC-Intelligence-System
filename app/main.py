"""
Ponto de entrada da aplicação GRC Intelligence System.
Backend Flask com rotas REST, autenticação por sessão e serving do frontend Vue.js.
"""
import os
from flask import Flask, send_from_directory, session, jsonify, request
from dotenv import load_dotenv

# Carrega variáveis de ambiente do arquivo .env
load_dotenv()

from app.database import init_db
from app.routers.clientes import clientes_bp
from app.routers.softwares import softwares_bp
from app.routers.instancias import instancias_bp
from app.routers.chat import chat_bp
from app.routers.politicas import politicas_bp
from app.routers.procedimentos import procedimentos_bp
from app.routers.governanca import governanca_bp
from app.routers.riscos import riscos_bp
from app.routers.auth import auth_bp
from app.routers.incidentes import incidentes_bp
from app.routers.plano_acoes import plano_acoes_bp
from app.routers.lgpd import lgpd_bp
from app.routers.treinamentos import treinamentos_bp
from app.routers.dashboard import dashboard_bp

# ─── Inicialização da Aplicação ───────────────────────────────────────────────

def create_app() -> Flask:
    """Factory function que cria e configura a aplicação Flask."""
    app = Flask(__name__, template_folder="templates")
    app.config["JSON_AS_ASCII"] = False  # Suporte a caracteres UTF-8 no JSON
    app.secret_key = os.getenv("SECRET_KEY", "grc-default-secret-key")

    # Inicializa o banco de dados
    with app.app_context():
        init_db()

    # ── Middleware de Autenticação ──────────────────────────────────────────
    @app.before_request
    def check_auth():
        """Verifica se o usuário está autenticado em todas as rotas /api/ (exceto login e health)."""
        rotas_livres = ["/api/auth/login", "/api/health", "/"]
        if request.path in rotas_livres or not request.path.startswith("/api/"):
            return  # Permite acesso sem autenticação
        if "user_id" not in session:
            return jsonify({"erro": "Não autenticado."}), 401

    # Registra os Blueprints
    app.register_blueprint(auth_bp)
    app.register_blueprint(clientes_bp)
    app.register_blueprint(softwares_bp)
    app.register_blueprint(instancias_bp)
    app.register_blueprint(chat_bp)
    app.register_blueprint(politicas_bp)
    app.register_blueprint(procedimentos_bp)
    app.register_blueprint(governanca_bp)
    app.register_blueprint(riscos_bp)
    app.register_blueprint(incidentes_bp)
    app.register_blueprint(plano_acoes_bp)
    app.register_blueprint(lgpd_bp)
    app.register_blueprint(treinamentos_bp)
    app.register_blueprint(dashboard_bp)

    # Serve o frontend Vue.js na rota raiz
    @app.route("/")
    def index():
        return send_from_directory(
            os.path.join(app.root_path, "templates"), "index.html"
        )

    # Health check
    @app.route("/api/health")
    def health():
        return jsonify({"status": "ok", "sistema": "GRC Intelligence System", "versao": "2.0.0"})

    return app


app = create_app()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
