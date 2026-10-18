#!/bin/sh
set -eu
# This probe briefly edits two source files and restores them on exit.
frontend_file=frontend/src/app/page.tsx
backend_file=backend/routes/api.php
probe_dir=$(mktemp -d)
cp "$frontend_file" "$probe_dir/page.tsx"
cp "$backend_file" "$probe_dir/api.php"
trap 'cp "$probe_dir/page.tsx" "$frontend_file"; cp "$probe_dir/api.php" "$backend_file"; rm -rf "$probe_dir"' EXIT HUP INT TERM
frontend_id=$(docker compose -f compose.yaml -f compose.dev.yaml ps -q frontend)
backend_id=$(docker compose -f compose.yaml -f compose.dev.yaml ps -q backend)
[ -n "$frontend_id" ] && [ -n "$backend_id" ]
sed 's/Ready to grow\./Hot reload verified./' "$probe_dir/page.tsx" > "$frontend_file"
sed "s/'status' => 'ok'/'status' => 'ok-hot-reload'/" "$probe_dir/api.php" > "$backend_file"
attempt=0
while [ "$attempt" -lt 30 ]; do
    if docker compose -f compose.yaml -f compose.dev.yaml exec -T nginx wget -qO- http://127.0.0.1/ | grep -q 'Hot reload verified.' \
       && docker compose -f compose.yaml -f compose.dev.yaml exec -T nginx wget -qO- http://127.0.0.1/api/health | grep -q 'ok-hot-reload'; then
        [ "$frontend_id" = "$(docker compose -f compose.yaml -f compose.dev.yaml ps -q frontend)" ]
        [ "$backend_id" = "$(docker compose -f compose.yaml -f compose.dev.yaml ps -q backend)" ]
        echo 'Frontend and PHP edits became visible without container recreation.'
        exit 0
    fi
    attempt=$((attempt + 1))
    sleep 1
done
echo 'Hot reload probe timed out. Source files will be restored.' >&2
exit 1
