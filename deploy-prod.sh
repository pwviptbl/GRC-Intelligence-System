#!/bin/bash
# Deploy em produção (Nginx + PHP-FPM, ver README > Produção).
# Uso: ./deploy-prod.sh [--assets]   (--assets força o build do Vite mesmo sem mudanças em resources/)
set -euo pipefail
cd "$(dirname "$0")"

GREEN='\033[0;32m'; BLUE='\033[0;34m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
step() { echo -e "${BLUE}▶ $*${NC}"; }

if ! grep -q "^COMPOSE_FILE=.*compose.prod.yaml" .env 2>/dev/null; then
    echo -e "${RED}❌ COMPOSE_FILE de produção não encontrado no .env. Para desenvolvimento, use ./deploy.sh.${NC}"
    exit 1
fi

artisan() { docker compose exec -T -u www-data -e HOME=/tmp php php artisan "$@"; }

FORCE_ASSETS=false
[ "${1:-}" = "--assets" ] && FORCE_ASSETS=true

step "Atualizando código (git pull --ff-only)"
OLD=$(git rev-parse HEAD)
git pull --ff-only
NEW=$(git rev-parse HEAD)
CHANGED=$(git diff --name-only "$OLD" "$NEW")
[ "$OLD" = "$NEW" ] && echo "  já estava na versão mais recente ($(git log --oneline -1))" || git log --oneline "$OLD..$NEW"

if echo "$CHANGED" | grep -q "^docker/prod/"; then
    step "Imagem PHP alterada: rebuild"
    docker compose build php
fi

step "Subindo containers"
docker compose up -d

step "Dependências PHP (composer install)"
docker compose exec -T -u www-data -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer php \
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress

if $FORCE_ASSETS || [ ! -f public/build/manifest.json ] || echo "$CHANGED" | grep -qE "^(resources/|package(-lock)?\.json|vite\.config\.js|tailwind\.config\.js|postcss\.config\.js)"; then
    step "Compilando assets (Vite)"
    docker run --rm -v "$PWD":/app -w /app node:22-alpine \
        sh -c "npm ci --no-audit --no-fund --silent && npm run build --silent"
    chown -R 1000:1000 public/build node_modules
else
    echo "  assets sem mudanças: build do Vite pulado (use --assets para forçar)"
fi

if artisan migrate:status 2>/dev/null | grep -q "Pending"; then
    step "Há migrações pendentes: backup antes de migrar"
    artisan backups:run
    artisan migrate --force
else
    echo "  nenhuma migração pendente"
fi

step "Atualizando caches do Laravel"
artisan optimize:clear >/dev/null
artisan optimize

step "Reiniciando scheduler e PHP-FPM"
docker compose restart scheduler php >/dev/null

step "Verificando saúde"
for i in $(seq 30); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' grc-intelligence-system-web-1 2>/dev/null)" = healthy ] && \
    [ "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${APP_PORT:-$(grep -oP '^APP_PORT=\K.*' .env)}/up")" = 200 ] && break
    sleep 2
done
CODE=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$(grep -oP '^APP_PORT=\K.*' .env)/up")
if [ "$CODE" = 200 ]; then
    echo -e "${GREEN}✅ Deploy concluído: $(git log --oneline -1)${NC}"
else
    echo -e "${RED}❌ /up respondeu $CODE. Veja: docker compose logs --tail=50 web php${NC}"
    exit 1
fi
