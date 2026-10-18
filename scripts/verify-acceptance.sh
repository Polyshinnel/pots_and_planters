#!/bin/sh
set -eu
# Run after the initial production-like startup and migrations.
# Uses named volumes throughout; no data deletion, schema rollback, or seeders.
docker compose up -d --wait --wait-timeout 180
sh scripts/verify-runtime.sh
docker compose exec -T backend php artisan infrastructure:database-fingerprint > /tmp/potsandplanters-migrations-before.txt
docker compose restart
docker compose up -d --wait --wait-timeout 180
docker compose up -d --force-recreate --wait --wait-timeout 180
docker compose exec -T backend php artisan infrastructure:database-fingerprint > /tmp/potsandplanters-migrations-after.txt
cmp /tmp/potsandplanters-migrations-before.txt /tmp/potsandplanters-migrations-after.txt
echo 'Migration data survived restart and recreation.'
sh scripts/verify-runtime.sh
# Move sequentially to dev, retaining the same persistent volumes.
docker compose down
docker compose -f compose.yaml -f compose.dev.yaml up -d --build --wait --wait-timeout 180
docker compose -f compose.yaml -f compose.dev.yaml exec -T backend php artisan test --compact
sh scripts/verify-dev-hmr.sh
sh scripts/verify-runtime.sh -f compose.yaml -f compose.dev.yaml
# Return to the production-like stack after successful checks.
docker compose -f compose.yaml -f compose.dev.yaml down
docker compose up -d --wait --wait-timeout 180
echo 'Automated acceptance checks completed; finish browser/admin verification in README.'
