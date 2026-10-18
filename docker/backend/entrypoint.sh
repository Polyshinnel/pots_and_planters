#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Generate it using the README command.' >&2
    exit 1
fi
# No migrations or shared cache clearing here: all three PHP services use this image.
mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
exec "$@"
