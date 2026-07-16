#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
STATUS_FILE="/tmp/grc-mcp-status.json"

read_env_value() {
    local key="$1"
    local line
    line="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 || true)"
    local value="${line#*=}"
    value="${value%\"}"
    value="${value#\"}"
    printf '%s' "$value"
}

upsert_env_value() {
    local key="$1"
    local value="$2"

    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

random_token() {
    openssl rand -hex 32
}

if [[ ! -f "$ENV_FILE" ]]; then
    cp "$ROOT_DIR/.env.example" "$ENV_FILE"
fi

token="$(read_env_value MCP_SERVER_TOKEN)"
if [[ -z "$token" ]]; then
    token="$(random_token)"
    upsert_env_value MCP_SERVER_TOKEN "$token"
    printf '[ok] MCP_SERVER_TOKEN gerado no .env\n'
fi

upsert_env_value MCP_ALLOW_UNAUTHENTICATED false

app_port="$(read_env_value APP_PORT)"
app_port="${app_port:-8088}"

cd "$ROOT_DIR"
docker compose up -d
docker compose exec -T laravel.test php artisan optimize:clear >/dev/null
docker compose exec -T laravel.test php artisan grc:mcp:validate

for attempt in $(seq 1 30); do
    if curl -sS -o /dev/null "http://127.0.0.1:${app_port}/up" 2>/dev/null; then
        break
    fi

    if [[ "$attempt" == "30" ]]; then
        printf '[erro] Laravel nao ficou pronto dentro do tempo esperado.\n' >&2
        exit 1
    fi

    sleep 1
done

http_code="$(curl -sS -o "$STATUS_FILE" -w '%{http_code}' \
    -X POST "http://127.0.0.1:${app_port}/mcp" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json, text/event-stream' \
    -H "Authorization: Bearer ${token}" \
    -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"grc-stack-status","version":"1.0"}}}')"

if [[ "$http_code" != "200" ]]; then
    printf '[erro] MCP HTTP retornou %s\n' "$http_code" >&2
    head -c 800 "$STATUS_FILE" >&2
    printf '\n' >&2
    exit 1
fi

printf '\nGRC web: http://127.0.0.1:%s\n' "$app_port"
printf 'GRC MCP: http://127.0.0.1:%s/mcp\n' "$app_port"
printf 'MCP initialize: HTTP %s\n' "$http_code"
printf 'Token: configurado em MCP_SERVER_TOKEN no .env\n'
printf '\nCodex stdio:\n'
printf 'codex mcp add grc -- php %s/artisan grc:mcp\n' "$ROOT_DIR"
printf '\nCodex HTTP:\n'
printf 'export GRC_MCP_TOKEN="$(grep "^MCP_SERVER_TOKEN=" .env | cut -d= -f2-)"\n'
printf 'codex mcp add grc-http --url http://127.0.0.1:%s/mcp --bearer-token-env-var GRC_MCP_TOKEN\n' "$app_port"
