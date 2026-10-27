#!/bin/bash
set -euo pipefail

cd /app

# Upload-Verzeichnisse existieren evtl. nicht, wenn ein leeres Volume gemountet ist
mkdir -p backend/uploads/pets backend/uploads/finder backend/logs
for f in default.svg default.jpg.svg; do
    [ -f "backend/uploads/pets/$f" ] || cp "docker/defaults/$f" "backend/uploads/pets/$f"
done

# Auf MySQL warten und Schema importieren, falls die Tabellen fehlen
php docker/init-db.php

# AI-Service (nur intern erreichbar)
(cd ai-service && exec python main.py) &
AI_PID=$!

# PHP Server: Frontend + API über router.php
php -S "0.0.0.0:${PORT}" -t frontend \
    -d upload_max_filesize=12M \
    -d post_max_size=16M \
    -d memory_limit=256M \
    -d max_execution_time=120 \
    router.php &
PHP_PID=$!

trap 'kill -TERM "$AI_PID" "$PHP_PID" 2>/dev/null' TERM INT

# Wenn einer der beiden Prozesse stirbt, Container beenden (Coolify startet neu)
EXIT_CODE=0
wait -n "$AI_PID" "$PHP_PID" || EXIT_CODE=$?
kill -TERM "$AI_PID" "$PHP_PID" 2>/dev/null || true
exit "$EXIT_CODE"
