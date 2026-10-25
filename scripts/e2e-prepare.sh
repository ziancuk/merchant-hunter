#!/usr/bin/env bash
# Prepares the isolated E2E database + frontend build. Idempotent: safe to run repeatedly.
# Produces database/e2e.sqlite (live DB) and database/e2e.base.sqlite (pristine snapshot restored before every spec file).
set -euo pipefail
cd "$(dirname "$0")/.."

export APP_ENV=e2e HUNTER_BASEMAP=local DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:-e2e-password-123}"
export DB_DATABASE="$PWD/database/e2e.sqlite"

[ -f .env ] || { cp .env.example .env && php artisan key:generate --no-interaction; }

rm -f "$DB_DATABASE" "$DB_DATABASE-wal" "$DB_DATABASE-shm"
touch "$DB_DATABASE"
php artisan migrate:fresh --seed --force --no-interaction
# Prospects come from hunter:demo-reset, which clears prospect data and runs hunter:seed-prospects --offline --seed=42.
php artisan hunter:demo-reset --no-interaction
cp "$DB_DATABASE" "$PWD/database/e2e.base.sqlite"

if [ "${E2E_SKIP_BUILD:-0}" != "1" ]; then
    npm run build
fi
