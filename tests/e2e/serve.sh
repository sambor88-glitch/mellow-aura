#!/usr/bin/env bash
# Starts the shop for the Playwright checkout test: a fresh SQLite database with the seeders,
# a panel account and `php artisan serve`. Playwright runs it through webServer in playwright.config.js.
# Environment variables beat .env, so a developer's own .env and database stay untouched.
set -euo pipefail

cd "$(dirname "$0")/../.."

: "${E2E_PORT:=8123}"
: "${E2E_ADMIN_EMAIL:=e2e@mellow-aura.test}"
: "${E2E_ADMIN_PASSWORD:?Set E2E_ADMIN_PASSWORD (at least 12 characters) for the panel account}"

export APP_ENV=local
export APP_DEBUG=true
# The Lighthouse budget puts a proxy in front and passes its address (tests/lighthouse/run.sh).
export APP_URL="${E2E_APP_URL:-http://127.0.0.1:${E2E_PORT}}"
export APP_LOCALES=pl,en
export STAGING_PASSWORD=
export DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/storage/e2e/database.sqlite"
export DB_URL=
export SESSION_DRIVER=file
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export MAIL_MAILER=log
export MAIL_REDIRECT_TO=
export LOG_CHANNEL=single
export LOG_LEVEL=debug
# No payment keys: TestGateway pays every order at once.
export PRZELEWY24_MERCHANT_ID= PRZELEWY24_POS_ID= PRZELEWY24_CRC= PRZELEWY24_API_KEY=

mkdir -p storage/e2e
rm -f "$DB_DATABASE"
touch "$DB_DATABASE"
# The test reads the mails from this log, so it starts empty.
: > storage/logs/laravel.log

php artisan storage:link --no-interaction || true
php artisan migrate:fresh --seed --force --no-interaction
# Vouchers and workshop dates for vouchers.spec.js (MA-263).
php artisan db:seed --class=Database\\Seeders\\E2eSeeder --force --no-interaction
printf '%s\n' "$E2E_ADMIN_PASSWORD" | php artisan admin:user "$E2E_ADMIN_EMAIL" --name=E2E --role=owner
# vouchers.spec.js buys signed in, one account per screen size: the shop's forms share one limit of 10 a minute,
# counted per address for a guest and per account for a signed-in user, and every test here comes from 127.0.0.1.
for screen in desktop iphone; do
    printf '%s\n' "$E2E_ADMIN_PASSWORD" | php artisan admin:user "e2e-${screen}@mellow-aura.test" --name="E2E ${screen}" --role=owner
done

exec php artisan serve --host=127.0.0.1 --port="$E2E_PORT" --no-reload
