#!/bin/bash
# Script de Deploy e Inicialização GRC Intelligence System (Laravel + Docker)

set -e

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Iniciando GRC Intelligence System (Versão Laravel)...${NC}"

# 0. Opção de Reset Total
echo -e "${YELLOW}❓ Deseja fazer um RESET TOTAL do ambiente? (Apagar containers, volumes e banco de dados) (s/n)${NC}"
read -r reset_response
DO_RESET=false
if [[ "$reset_response" =~ ^([sS][imIM]|[sS])$ ]]; then
    echo -e "${RED}⚠️  APAGANDO TUDO EM 3 SEGUNDOS...${NC}"
    sleep 3
    ./vendor/bin/sail down -v || true
    echo -e "${GREEN}✅ Ambiente limpo!${NC}"
    DO_RESET=true
fi

# 1. Verifica/Cria .env
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚠️  Arquivo .env não encontrado!${NC}"
    cp .env.example .env
    echo -e "${GREEN}✅ Arquivo .env criado a partir do .example.${NC}"
    echo -e "${YELLOW}👉 Lembre-se de configurar sua GEMINI_API_KEY no .env!${NC}"
fi

# 2. Sobe os containers usando o Sail (Docker Compose)
echo -e "${BLUE}📦 Subindo containers via Docker Compose...${NC}"
./vendor/bin/sail up -d

# 3. Aguarda o PostgreSQL ficar pronto
echo -e "${BLUE}⏳ Aguardando banco de dados estabilizar...${NC}"
until ./vendor/bin/sail artisan db:monitor --databases pgsql > /dev/null 2>&1; do
  echo -n "."
  sleep 2
done
echo -e "\n${GREEN}✅ Banco de dados online!${NC}"

# 4. Instala dependências e roda migrações
echo -e "${BLUE}🔄 Executando migrações e atualizando ambiente...${NC}"
./vendor/bin/sail composer install --quiet
./vendor/bin/sail artisan key:generate --quiet
./vendor/bin/sail artisan migrate --force --quiet

# Se resetou, cria o admin e o guia LGPD obrigatoriamente (Dados essenciais do sistema)
if [ "$DO_RESET" = true ]; then
    echo -e "${BLUE}👤 Criando usuário administrador inicial...${NC}"
    ./vendor/bin/sail artisan db:seed --class=AdminSeeder --force
    echo -e "${BLUE}📋 Carregando Guia de Auditoria LGPD...${NC}"
    ./vendor/bin/sail artisan db:seed --class=LgpdSeeder --force
fi

# 5. Compila Assets (Vite)
echo -e "${BLUE}🎨 Instalando dependências Node e compilando assets...${NC}"
./vendor/bin/sail npm install --quiet
./vendor/bin/sail npm run build --quiet

# 6. Pergunta se deseja popular o banco com dados de TESTE (fictícios)
echo -e "${YELLOW}❓ Deseja popular o banco com dados de teste (Clientes, Softwares, Riscos fictícios)? (s/n)${NC}"
read -r response
if [[ "$response" =~ ^([sS][imIM]|[sS])$ ]]; then
    ./vendor/bin/sail artisan db:seed --force
    echo -e "${GREEN}✅ Banco de dados populado com dados de demonstração!${NC}"
fi

echo -e "--------------------------------------------------------"
echo -e "${GREEN}🔥 Sistema GRC Intelligence Online!${NC}"
echo -e "Acesse em: ${BLUE}http://localhost${NC}"
echo -e "Usuário: ${YELLOW}admin@admin.com${NC} | Senha: ${YELLOW}admin123${NC}"
echo -e "Para ver os logs, use: ${BLUE}./vendor/bin/sail logs -f${NC}"
echo -e "Para parar o sistema, use: ${BLUE}./vendor/bin/sail stop${NC}"
echo -e "--------------------------------------------------------"
