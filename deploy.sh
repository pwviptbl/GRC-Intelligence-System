#!/bin/bash
# Script de inicialização e "deploy" local para o GRC Intelligence System

set -e

echo "🚀 Iniciando ambiente local GRC Intelligence System..."

if [ ! -f .env ]; then
    echo "⚠️ Arquivo .env não encontrado!"
    echo "📋 Criando .env com configurações padrão..."
    cat <<EOF > .env
DEFAULT_USER=admin
DEFAULT_PASSWORD=dbseller2026
SECRET_KEY=uma-chave-secreta-muito-segura-aqui
EOF
    echo "✅ Arquivo .env criado."
fi

# Verifica o virtual environment
if [ ! -d "venv" ]; then
    echo "📦 Criando ambiente virtual (venv)..."
    python3 -m venv venv
fi

echo "🔄 Instalando/Atualizando dependências... Por favor, aguarde."
source venv/bin/activate
pip install -r requirements.txt --upgrade --quiet

echo "🗄️ Verificando banco de dados..."
if [ ! -f grc.db ]; then
    echo "Criando o banco de dados inicial (vazio)..."
    python -c "from app.database import init_db; init_db()"
fi

echo "✅ Ambiente pronto!"
echo "🔥 Servindo a aplicação via Flask na porta 5001"
echo "O acesso ficará disponível em suas interfaces: http://127.0.0.1:5001"
echo "Para cancelar as execuções e sair do sistema, pressione CTRL+C."
echo "--------------------------------------------------------"

# Mata qualquer processo anterior rodando nesta porta
fuser -k 5001/tcp 2>/dev/null || true

python -m flask --app app.main run --port 5001 --host 0.0.0.0
