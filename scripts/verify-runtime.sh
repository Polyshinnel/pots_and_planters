#!/bin/sh
set -eu
# Run from the repository root after startup and initial migrations.
# Pass Compose override files as arguments, e.g. -f compose.yaml -f compose.dev.yaml.
docker compose "$@" config --quiet
docker compose "$@" ps
docker compose "$@" exec -T nginx nginx -t
docker compose "$@" exec -T nginx wget -qO- http://127.0.0.1/
docker compose "$@" exec -T nginx wget -qO- http://127.0.0.1/api/health
docker compose "$@" exec -T backend php artisan migrate:status
docker compose "$@" exec -T backend php artisan infrastructure:ready
docker compose "$@" exec -T horizon php artisan horizon:status
docker compose "$@" exec -T backend php artisan infrastructure:queue-check
docker compose "$@" exec -T scheduler php artisan infrastructure:scheduler-status
docker compose "$@" exec -T frontend node -p 'process.version'
docker compose "$@" exec -T backend php -v
