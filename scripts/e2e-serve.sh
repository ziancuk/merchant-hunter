#!/usr/bin/env bash
# Playwright webServer entry: prepare DB + build, then serve on 127.0.0.1:8001.
set -euo pipefail
cd "$(dirname "$0")/.."
bash scripts/e2e-prepare.sh
export APP_ENV=e2e HUNTER_BASEMAP=local DEMO_USER_PASSWORD="${DEMO_USER_PASSWORD:-e2e-password-123}"
export DB_DATABASE="$PWD/database/e2e.sqlite"
exec php artisan serve --host=127.0.0.1 --port=8001 --no-reload
