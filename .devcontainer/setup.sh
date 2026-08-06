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
php artisan db:seed --class=DemoSeeder --force

npm install
npm run build

echo ""
echo "Setup complete. Start the app with:"
echo "  php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "Demo login: demo@example.com / password (admin, so you can see the pricing page too)"
echo "3 fake [DEMO] plans are seeded so the storefront isn't empty."
echo ""
echo "Note: without real VMOS_ACCESS_KEY/SECRET_KEY in .env, buying a plan or running"
echo "'vmos:sync-skus' will fail — this is a UI/flow preview only, not a live store."
