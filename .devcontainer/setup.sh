#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

composer install --no-interaction

[ -f .env ] || cp .env.example .env

# SQLite for Codespaces testing — no real MySQL/VMOS/crypto credentials needed
# to just click around the storefront UI.
mkdir -p database
touch database/database.sqlite

php artisan key:generate --ansi
php artisan migrate --force

npm install
npm run build

echo ""
echo "Setup complete. Start the app with:"
echo "  php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "Note: without real VMOS_ACCESS_KEY/SECRET_KEY in .env, you can browse/register/login"
echo "but 'vmos:sync-skus' and any real purchase flow will fail (nothing to sell yet)."
