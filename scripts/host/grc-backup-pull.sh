#!/bin/bash
# Copia os backups do GRC Intelligence System (LXC 104) para o host Proxmox.
# Modelo "pull": o host busca os arquivos; o container não tem acesso ao destino,
# então um container comprometido não consegue apagar ou sobrescrever estas cópias.
set -euo pipefail

CT="${GRC_CT:-104}"
SRC="${GRC_BACKUP_SRC:-/opt/GRC-Intelligence-System/storage/app/private/backups}"
DEST="${GRC_BACKUP_DEST:-/var/backups/grc-intel}"
RETENTION_DAYS="${GRC_BACKUP_RETENTION_DAYS:-180}"
# Se definido, exige que este caminho seja um ponto de montagem ativo (evita gravar no disco do sistema
# quando o disco de backup não montou).
REQUIRE_MOUNT="${GRC_BACKUP_REQUIRE_MOUNT:-}"

if [ -n "$REQUIRE_MOUNT" ] && ! mountpoint -q "$REQUIRE_MOUNT"; then
    echo "ERRO: $REQUIRE_MOUNT não está montado; cópia cancelada" >&2
    exit 1
fi

if [ "$(pct status "$CT" | awk '{print $2}')" != "running" ]; then
    echo "ERRO: container $CT não está rodando" >&2
    exit 1
fi

install -d -m 700 "$DEST"

# --skip-old-files: arquivos já copiados nunca são sobrescritos (cópias imutáveis no host).
pct exec "$CT" -- sh -c "cd '$SRC' && tar -cf - grc-backup-*.zip grc-backup-*.zip.meta.json 2>/dev/null" \
    | tar -C "$DEST" --skip-old-files --no-same-owner -xf -

# Confere o checksum de cada ZIP contra o registrado no .meta.json.
failed=0
for zip in "$DEST"/grc-backup-*.zip; do
    [ -e "$zip" ] || continue
    expected="$(grep -o '"checksum": *"[0-9a-f]*"' "$zip.meta.json" 2>/dev/null | grep -o '[0-9a-f]\{64\}' || true)"
    [ -n "$expected" ] || { echo "AVISO: sem checksum registrado para $(basename "$zip")"; continue; }
    actual="$(sha256sum "$zip" | cut -d' ' -f1)"
    if [ "$expected" != "$actual" ]; then
        echo "ERRO: checksum divergente em $(basename "$zip")" >&2
        failed=1
    fi
done

# Retenção no host: remove cópias mais antigas que RETENTION_DAYS.
find "$DEST" -maxdepth 1 -name 'grc-backup-*.zip' -mtime +"$RETENTION_DAYS" -print -exec rm -f {} {}.meta.json \;

echo "OK: $(ls "$DEST"/grc-backup-*.zip 2>/dev/null | wc -l) backups em $DEST ($(du -sh "$DEST" | cut -f1))"
exit "$failed"
