"""
Ponto de entrada da aplicação GRC Intelligence System.
Backend Flask com rotas REST e serving do frontend Vue.js.
"""
import os
from flask import Flask, send_from_directory
from dotenv import load_dotenv

# Carrega variáveis de ambiente do arquivo .env
load_dotenv()

from app.database import init_db
from app.routers.clientes import clientes_bp
from app.routers.softwares import softwares_bp
from app.routers.instancias import instancias_bp
from app.routers.chat import chat_bp

# ─── Inicialização da Aplicação ───────────────────────────────────────────────

def create_app() -> Flask:
    """Factory function que cria e configura a aplicação Flask."""
    app = Flask(__name__, template_folder="templates")
    app.config["JSON_AS_ASCII"] = False  # Suporte a caracteres UTF-8 no JSON

    # Inicializa o banco de dados
    with app.app_context():
        init_db()

    # Registra os Blueprints
    app.register_blueprint(clientes_bp)
    app.register_blueprint(softwares_bp)
    app.register_blueprint(instancias_bp)
    app.register_blueprint(chat_bp)

    # Serve o frontend Vue.js na rota raiz
    @app.route("/")
    def index():
        return send_from_directory(
            os.path.join(app.root_path, "templates"), "index.html"
        )

    # Health check
    @app.route("/api/health")
    def health():
        from flask import jsonify
        return jsonify({"status": "ok", "sistema": "GRC Intelligence System", "versao": "1.0.0"})

    return app


app = create_app()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
