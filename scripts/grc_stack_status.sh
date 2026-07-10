#!/usr/bin/env bash

set -u

OPEN_GRC_MCP=0
for arg in "$@"; do
    case "$arg" in
        --open-grc-mcp)
            OPEN_GRC_MCP=1
            ;;
        --help|-h)
            cat <<'EOF'
Uso:
  ./scripts/grc_stack_status.sh
  ./scripts/grc_stack_status.sh --open-grc-mcp

Opcoes:
  --open-grc-mcp   Desabilita temporariamente o bearer token do MCP do GRC
                   para testes com clientes que nao suportam Bearer token.
                   O Host Agent continua protegido.
EOF
            exit 0
            ;;
    esac
done

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
HOST_AGENT_DIR="$ROOT_DIR/host-agent"
HOST_AGENT_ENV_FILE="$HOME/.config/grc-host-agent.env"
HOST_AGENT_SERVICE_SRC="$HOST_AGENT_DIR/systemd/grc-host-agent.service"
HOST_AGENT_SERVICE_DST="$HOME/.config/systemd/user/grc-host-agent.service"
NGROK_RUNTIME_DIR="$HOME/.cache/grc-intelligence-system"
NGROK_CONFIG_FILE="$NGROK_RUNTIME_DIR/ngrok.yml"
NGROK_PID_FILE="$NGROK_RUNTIME_DIR/ngrok.pid"
NGROK_LOG_FILE="$NGROK_RUNTIME_DIR/ngrok.log"
STATUS_TMP_DIR="/tmp/grc-stack-status"
mkdir -p "$STATUS_TMP_DIR"
mkdir -p "$NGROK_RUNTIME_DIR"

RED="$(printf '\033[31m')"
GREEN="$(printf '\033[32m')"
YELLOW="$(printf '\033[33m')"
BLUE="$(printf '\033[34m')"
BOLD="$(printf '\033[1m')"
RESET="$(printf '\033[0m')"

log() {
    printf "%b[%s]%b %s\n" "$BLUE" "grc-stack" "$RESET" "$1"
}

ok() {
    printf "%b[ok]%b %s\n" "$GREEN" "$RESET" "$1"
}

warn() {
    printf "%b[warn]%b %s\n" "$YELLOW" "$RESET" "$1"
}

fail() {
    printf "%b[fail]%b %s\n" "$RED" "$RESET" "$1" >&2
}

trim() {
    local value="$1"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    printf '%s' "$value"
}

read_env_value() {
    local file="$1"
    local key="$2"
    if [[ ! -f "$file" ]]; then
        return 1
    fi

    local line
    line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
    if [[ -z "$line" ]]; then
        return 1
    fi

    local value="${line#*=}"
    value="${value%\"}"
    value="${value#\"}"
    printf '%s' "$value"
}

upsert_env_value() {
    local file="$1"
    local key="$2"
    local value="$3"

    mkdir -p "$(dirname "$file")"
    touch "$file"

    if grep -qE "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$file"
    else
        printf "%s=%s\n" "$key" "$value" >> "$file"
    fi
}

random_token() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 24
        return
    fi

    date +%s%N | sha256sum | awk '{print $1}'
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

install_package() {
    local package="$1"
    if command_exists apt-get; then
        sudo apt-get update
        sudo apt-get install -y "$package"
        return $?
    fi

    fail "Nao consegui instalar $package automaticamente."
    return 1
}

ensure_command() {
    local command_name="$1"
    local package_name="$2"

    if command_exists "$command_name"; then
        ok "Dependencia presente: $command_name"
        return 0
    fi

    warn "Dependencia ausente: $command_name. Tentando instalar $package_name."
    install_package "$package_name"
}

python_has_module() {
    local module_name="$1"
    python3 -c "import ${module_name}" >/dev/null 2>&1
}

pick_free_port() {
    local port="$1"
    while ss -ltn "( sport = :$port )" | grep -q ":$port"; do
        port=$((port + 1))
    done
    printf '%s' "$port"
}

http_code() {
    local url="$1"
    shift
    curl -sS -o /dev/null -w '%{http_code}' "$@" "$url"
}

ensure_dotenv() {
    if [[ -f "$ENV_FILE" ]]; then
        ok ".env presente"
        return
    fi

    if [[ -f "$ROOT_DIR/.env.example" ]]; then
        cp "$ROOT_DIR/.env.example" "$ENV_FILE"
        ok ".env criado a partir de .env.example"
        return
    fi

    fail "Nao encontrei .env nem .env.example"
    exit 1
}

ensure_mcp_tokens() {
    local grc_token
    grc_token="$(read_env_value "$ENV_FILE" "MCP_SERVER_TOKEN" || true)"
    if [[ "$OPEN_GRC_MCP" == "1" ]]; then
        if [[ -n "$grc_token" ]]; then
            upsert_env_value "$ENV_FILE" "MCP_SERVER_TOKEN" ""
            ok "MCP_SERVER_TOKEN removido temporariamente para teste aberto"
        else
            ok "MCP do GRC ja esta sem autenticacao"
        fi
        GRC_MCP_TOKEN=""
    elif [[ -z "$grc_token" ]]; then
        grc_token="$(random_token)"
        upsert_env_value "$ENV_FILE" "MCP_SERVER_TOKEN" "$grc_token"
        ok "MCP_SERVER_TOKEN gerado no .env"
        GRC_MCP_TOKEN="$grc_token"
    else
        ok "MCP_SERVER_TOKEN ja configurado"
        GRC_MCP_TOKEN="$grc_token"
    fi

    local allowed_origins
    allowed_origins="$(read_env_value "$ENV_FILE" "MCP_ALLOWED_ORIGINS" || true)"
    if [[ -z "$allowed_origins" ]]; then
        upsert_env_value "$ENV_FILE" "MCP_ALLOWED_ORIGINS" "https://chatgpt.com"
        ok "MCP_ALLOWED_ORIGINS ajustado para https://chatgpt.com"
    else
        ok "MCP_ALLOWED_ORIGINS ja configurado"
    fi

    local host_token
    host_token="$(read_env_value "$HOST_AGENT_ENV_FILE" "HOST_MCP_TOKEN" || true)"
    if [[ -z "$host_token" ]]; then
        host_token="$(random_token)"
        upsert_env_value "$HOST_AGENT_ENV_FILE" "HOST_MCP_TOKEN" "$host_token"
        ok "HOST_MCP_TOKEN gerado em $HOST_AGENT_ENV_FILE"
    else
        ok "HOST_MCP_TOKEN ja configurado"
    fi
    HOST_MCP_TOKEN="$host_token"
}

ensure_php_deps() {
    if [[ -f "$ROOT_DIR/vendor/autoload.php" ]]; then
        ok "Dependencias PHP ja instaladas"
        return
    fi

    warn "vendor/autoload.php ausente. Tentando composer install no container."
    docker compose up -d
    docker compose exec -T laravel.test composer install --no-interaction
}

ensure_js_deps() {
    if [[ -d "$ROOT_DIR/node_modules" ]]; then
        ok "Dependencias Node ja instaladas"
        return
    fi

    warn "node_modules ausente. Tentando npm install."
    (cd "$ROOT_DIR" && npm install)
}

ensure_python_deps() {
    if python_has_module mcp && python_has_module uvicorn; then
        ok "Dependencias Python do host-agent ja instaladas"
        return
    fi

    warn "Dependencias Python do host-agent ausentes. Tentando instalar."
    python3 -m pip install -r "$HOST_AGENT_DIR/requirements.txt"
}

ensure_app_key() {
    local app_key
    app_key="$(read_env_value "$ENV_FILE" "APP_KEY" || true)"
    if [[ -n "$app_key" ]]; then
        ok "APP_KEY presente"
        return
    fi

    warn "APP_KEY ausente. Gerando chave."
    php "$ROOT_DIR/artisan" key:generate --force
}

ensure_docker_stack() {
    if docker compose ps laravel.test 2>/dev/null | grep -q "Up"; then
        ok "Laravel Sail ja esta rodando"
    else
        warn "Laravel Sail parado. Subindo stack."
        docker compose up -d
    fi
}

install_host_agent_service() {
    mkdir -p "$(dirname "$HOST_AGENT_SERVICE_DST")"
    if [[ ! -f "$HOST_AGENT_SERVICE_DST" ]] || ! cmp -s "$HOST_AGENT_SERVICE_SRC" "$HOST_AGENT_SERVICE_DST"; then
        cp "$HOST_AGENT_SERVICE_SRC" "$HOST_AGENT_SERVICE_DST"
        ok "Unit do host-agent instalada/atualizada"
    else
        ok "Unit do host-agent ja esta instalada"
    fi
}

restart_host_agent_service() {
    systemctl --user daemon-reload
    systemctl --user enable --now grc-host-agent >/dev/null
    systemctl --user restart grc-host-agent >/dev/null
    ok "Host Agent iniciado/reiniciado"
}

wait_for_http() {
    local url="$1"
    local attempts="${2:-20}"
    local sleep_seconds="${3:-1}"
    local i

    for ((i = 1; i <= attempts; i++)); do
        if curl -sS "$url" >/dev/null 2>&1; then
            return 0
        fi
        sleep "$sleep_seconds"
    done

    return 1
}

reload_laravel_config() {
    docker compose exec -T laravel.test php artisan optimize:clear >/dev/null || true
}

probe_grc_http() {
    local app_port="$1"
    local app_base="http://127.0.0.1:${app_port}"
    local output_file="$STATUS_TMP_DIR/grc-mcp.json"

    local code
    local curl_args=(
        -sS
        -o "$output_file"
        -w '%{http_code}'
        -X POST "${app_base}/mcp"
        -H 'Content-Type: application/json'
        -H 'Accept: application/json, text/event-stream'
        -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"grc-stack-status","version":"1.0"}}}'
    )

    if [[ -n "$GRC_MCP_TOKEN" ]]; then
        curl_args+=(-H "Authorization: Bearer ${GRC_MCP_TOKEN}")
    fi

    code="$(curl "${curl_args[@]}")"

    GRC_MCP_HTTP_CODE="$code"
    GRC_MCP_HTTP_BODY="$(cat "$output_file" 2>/dev/null || true)"
}

probe_host_agent_http() {
    local port="$1"
    local output_file="$STATUS_TMP_DIR/host-mcp.json"

    local code
    code="$(curl -sS -o "$output_file" -w '%{http_code}' \
        -X POST "http://127.0.0.1:${port}/mcp" \
        -H 'Content-Type: application/json' \
        -H 'Accept: application/json, text/event-stream' \
        -H "Authorization: Bearer ${HOST_MCP_TOKEN}" \
        -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"grc-stack-status","version":"1.0"}}}')"

    HOST_MCP_HTTP_CODE="$code"
    HOST_MCP_HTTP_BODY="$(cat "$output_file" 2>/dev/null || true)"
}

ngrok_has_authtoken() {
    local ngrok_config
    ngrok_config="$(get_ngrok_config_path || true)"
    if [[ -z "$ngrok_config" || ! -f "$ngrok_config" ]]; then
        return 1
    fi

    grep -q "authtoken:" "$ngrok_config"
}

get_ngrok_config_path() {
    ngrok config check 2>/dev/null | awk '/Valid configuration file at /{print $5}' | tail -n 1
}

get_ngrok_authtoken() {
    local ngrok_config
    ngrok_config="$(get_ngrok_config_path || true)"
    if [[ -z "$ngrok_config" || ! -f "$ngrok_config" ]]; then
        return 1
    fi

    awk '/authtoken:/{print $2}' "$ngrok_config" | tail -n 1
}

start_ngrok_tunnels() {
    local grc_port="$1"
    local host_port="$2"

    if ! command_exists ngrok; then
        warn "ngrok nao encontrado. Pulando tunel."
        return 0
    fi

    if ! ngrok_has_authtoken; then
        warn "ngrok sem authtoken configurado. Pulando tunel."
        return 0
    fi

    local authtoken
    authtoken="$(get_ngrok_authtoken || true)"
    if [[ -z "$authtoken" ]]; then
        warn "Nao consegui ler o authtoken do ngrok. Pulando tunel."
        return 0
    fi

    local base_config
    base_config="$(get_ngrok_config_path || true)"
    if [[ -n "$base_config" ]]; then
        NGROK_RUNTIME_DIR="$(dirname "$base_config")"
        NGROK_CONFIG_FILE="$NGROK_RUNTIME_DIR/grc-stack.yml"
        NGROK_PID_FILE="$NGROK_RUNTIME_DIR/grc-stack.pid"
        NGROK_LOG_FILE="$NGROK_RUNTIME_DIR/grc-stack.log"
        mkdir -p "$NGROK_RUNTIME_DIR"
    fi

    local api_port
    api_port="$(pick_free_port 4040)"
    NGROK_API_PORT="$api_port"

    cat > "$NGROK_CONFIG_FILE" <<EOF
version: "3"
agent:
  authtoken: ${authtoken}
  web_addr: 127.0.0.1:${api_port}
tunnels:
  grc:
    proto: http
    addr: 127.0.0.1:${grc_port}
  host-agent:
    proto: http
    addr: 127.0.0.1:${host_port}
EOF

    if [[ -f "$NGROK_PID_FILE" ]]; then
        local old_pid
        old_pid="$(cat "$NGROK_PID_FILE" 2>/dev/null || true)"
        if [[ -n "$old_pid" ]] && kill -0 "$old_pid" 2>/dev/null; then
            kill "$old_pid" 2>/dev/null || true
            sleep 1
        fi
    fi

    nohup ngrok start --all --config "$NGROK_CONFIG_FILE" >"$NGROK_LOG_FILE" 2>&1 &
    echo $! > "$NGROK_PID_FILE"

    local attempts=60
    local i
    for ((i = 1; i <= attempts; i++)); do
        if curl -sS "http://127.0.0.1:${api_port}/api/tunnels" > "$STATUS_TMP_DIR/ngrok-tunnels.json" 2>/dev/null; then
            if grep -q '"tunnels"' "$STATUS_TMP_DIR/ngrok-tunnels.json"; then
                break
            fi
        fi
        sleep 1
    done

    if [[ ! -s "$STATUS_TMP_DIR/ngrok-tunnels.json" ]]; then
        warn "API do ngrok nao respondeu."
        return 0
    fi

    NGROK_GRC_URL="$(python3 - <<'PY' "$STATUS_TMP_DIR/ngrok-tunnels.json"
import json, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    print("")
    raise SystemExit(0)
for item in data.get("tunnels", []):
    if item.get("name") == "grc":
        print(item.get("public_url", ""))
        break
PY
)"

    NGROK_HOST_URL="$(python3 - <<'PY' "$STATUS_TMP_DIR/ngrok-tunnels.json"
import json, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    print("")
    raise SystemExit(0)
for item in data.get("tunnels", []):
    if item.get("name") == "host-agent":
        print(item.get("public_url", ""))
        break
PY
)"

    if [[ -n "$NGROK_GRC_URL" || -n "$NGROK_HOST_URL" ]]; then
        ok "ngrok iniciado"
    else
        warn "ngrok iniciou, mas nao retornou URLs publicas."
    fi
}

print_summary() {
    local app_port="$1"
    local host_port="$2"

    printf "\n%bResumo operacional%b\n" "$BOLD" "$RESET"
    printf "Projeto: %s\n" "$ROOT_DIR"
    printf "GRC web local: http://127.0.0.1:%s\n" "$app_port"
    printf "GRC MCP local: http://127.0.0.1:%s/mcp\n" "$app_port"
    printf "Host Agent local: http://127.0.0.1:%s/mcp\n" "$host_port"
    printf "Login padrao: admin@admin.com\n"
    printf "Senha padrao: admin123\n"
    if [[ -n "$GRC_MCP_TOKEN" ]]; then
        printf "Token MCP GRC: %s\n" "$GRC_MCP_TOKEN"
    else
        printf "Token MCP GRC: desabilitado temporariamente\n"
    fi
    printf "Token Host Agent: %s\n" "$HOST_MCP_TOKEN"

    printf "\n%bStatus%b\n" "$BOLD" "$RESET"
    printf "Docker app: %s\n" "$(docker compose ps laravel.test | awk 'NR==2 {print $4" "$5" "$6}' | sed 's/[[:space:]]*$//' || true)"
    printf "Host Agent systemd: %s\n" "$(systemctl --user is-active grc-host-agent 2>/dev/null || true)"
    printf "GRC MCP HTTP initialize: HTTP %s\n" "${GRC_MCP_HTTP_CODE:-n/a}"
    printf "Host MCP HTTP initialize: HTTP %s\n" "${HOST_MCP_HTTP_CODE:-n/a}"

    if [[ -n "${NGROK_GRC_URL:-}" ]]; then
        printf "ngrok GRC: %s/mcp\n" "$NGROK_GRC_URL"
    else
        printf "ngrok GRC: indisponivel\n"
    fi

    if [[ -n "${NGROK_HOST_URL:-}" ]]; then
        printf "ngrok Host Agent: %s/mcp\n" "$NGROK_HOST_URL"
    else
        printf "ngrok Host Agent: indisponivel\n"
    fi

    printf "\n%bConectar no Codex local%b\n" "$BOLD" "$RESET"
    printf "GRC stdio: codex mcp add grc -- php %s/artisan grc:mcp\n" "$ROOT_DIR"
    printf "GRC http: codex mcp add grc-http --url http://127.0.0.1:%s/mcp --bearer-token-env-var GRC_MCP_TOKEN\n" "$app_port"
    printf "Host http: codex mcp add host-agent --url http://127.0.0.1:%s/mcp --bearer-token-env-var HOST_MCP_TOKEN\n" "$host_port"

    printf "\n%bConectar no ChatGPT web via tunel%b\n" "$BOLD" "$RESET"
    if [[ -n "${NGROK_GRC_URL:-}" ]]; then
        printf "GRC URL: %s/mcp\n" "$NGROK_GRC_URL"
    fi
    if [[ -n "${NGROK_HOST_URL:-}" ]]; then
        printf "Host URL: %s/mcp\n" "$NGROK_HOST_URL"
    fi
    if [[ -n "$GRC_MCP_TOKEN" ]]; then
        printf "Bearer GRC: %s\n" "$GRC_MCP_TOKEN"
    else
        printf "Bearer GRC: nao usar neste teste\n"
    fi
    printf "Bearer Host: %s\n" "$HOST_MCP_TOKEN"
    printf "Header MCP: MCP-Protocol-Version: 2025-11-25\n"

    printf "\n%bObservacoes%b\n" "$BOLD" "$RESET"
    if [[ "$OPEN_GRC_MCP" == "1" ]]; then
        printf -- "- O GRC HTTP esta temporariamente sem autenticacao para teste com o Claude.\n"
        printf -- "- Rode o script novamente sem --open-grc-mcp para restaurar o bearer token do GRC.\n"
    else
        printf -- "- O GRC HTTP usa bearer token; sem ele o /mcp nao deve responder a clientes remotos.\n"
    fi
    printf -- "- Ferramentas de escrita do GRC seguem em dry-run por padrao e exigem confirm=true.\n"
    printf -- "- O Host Agent executa no Linux real com as permissoes do usuario %s.\n" "$USER"
    printf -- "- Logs do ngrok: %s\n" "$NGROK_LOG_FILE"
}

main() {
    log "Validando dependencias basicas"
    ensure_command curl curl || exit 1
    ensure_command docker docker.io || exit 1
    ensure_command python3 python3 || exit 1
    ensure_command systemctl systemd || exit 1

    ensure_dotenv
    ensure_mcp_tokens

    log "Garantindo dependencias do projeto"
    ensure_php_deps || exit 1
    ensure_js_deps || exit 1
    ensure_python_deps || exit 1
    ensure_app_key || exit 1

    log "Subindo GRC no Docker"
    ensure_docker_stack || exit 1
    reload_laravel_config

    log "Instalando e reiniciando o Host Agent"
    install_host_agent_service || exit 1
    restart_host_agent_service || exit 1

    local app_port
    app_port="$(read_env_value "$ENV_FILE" "APP_PORT" || true)"
    app_port="$(trim "${app_port:-8088}")"
    if [[ -z "$app_port" ]]; then
        app_port="8088"
    fi

    local host_port
    host_port="$(read_env_value "$HOST_AGENT_ENV_FILE" "HOST_MCP_PORT" || true)"
    host_port="$(trim "${host_port:-8765}")"
    if [[ -z "$host_port" ]]; then
        host_port="8765"
    fi

    log "Testando endpoints HTTP"
    wait_for_http "http://127.0.0.1:${host_port}/mcp" 15 1 || true
    probe_grc_http "$app_port"
    probe_host_agent_http "$host_port"

    if [[ "${GRC_MCP_HTTP_CODE:-}" != "200" ]]; then
        warn "O GRC MCP HTTP nao respondeu 200. Corpo resumido:"
        printf "%s\n" "${GRC_MCP_HTTP_BODY:-}" | head -c 400
        printf "\n"
    else
        ok "GRC MCP HTTP respondeu initialize com 200"
    fi

    if [[ "${HOST_MCP_HTTP_CODE:-}" != "200" ]]; then
        warn "O Host Agent MCP HTTP nao respondeu 200. Corpo resumido:"
        printf "%s\n" "${HOST_MCP_HTTP_BODY:-}" | head -c 400
        printf "\n"
    else
        ok "Host Agent MCP HTTP respondeu initialize com 200"
    fi

    log "Tentando subir ngrok"
    start_ngrok_tunnels "$app_port" "$host_port"

    print_summary "$app_port" "$host_port"
}

main "$@"
