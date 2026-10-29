#!/usr/bin/env bash
# Lighthouse budget (MA-267): the shop from tests/e2e/serve.sh behind a compressing proxy,
# then lighthouserc.cjs on phone and desktop. Both presets always run; the script fails when either
# misses a threshold. Reports and summary.md land in lighthouse-results/.
set -uo pipefail

cd "$(dirname "$0")/../.."

: "${E2E_PORT:=8123}"
: "${LIGHTHOUSE_PORT:=8124}"
export E2E_PORT LIGHTHOUSE_PORT
# Lighthouse opens the pages through the proxy, so links and canonical URLs point there.
export E2E_APP_URL="http://127.0.0.1:${LIGHTHOUSE_PORT}"
# As on production: indexable pages, so the SEO score is the real one.
export APP_NOINDEX=false
# The shop needs a panel account; nobody logs in here, so a random password is enough.
: "${E2E_ADMIN_PASSWORD:=$(openssl rand -hex 16)}"
export E2E_ADMIN_PASSWORD
# php artisan serve handles requests one at a time without this; the browser loads the assets in parallel.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

rm -rf lighthouse-results .lighthouseci
mkdir -p lighthouse-results

bash tests/e2e/serve.sh > lighthouse-results/server.log 2>&1 &
server=$!
node tests/lighthouse/compress-proxy.mjs "$LIGHTHOUSE_PORT" "$E2E_PORT" &
proxy=$!
trap 'kill "$proxy" "$server" 2>/dev/null; pkill -P "$server" 2>/dev/null' EXIT

for _ in $(seq 1 120); do
    curl -fsS "http://127.0.0.1:${LIGHTHOUSE_PORT}/up" > /dev/null 2>&1 && break
    kill -0 "$server" 2>/dev/null || { cat lighthouse-results/server.log; exit 1; }
    sleep 1
done

status=0
for preset in mobile desktop; do
    rm -rf .lighthouseci
    LIGHTHOUSE_PRESET=$preset npx lhci collect || status=1
    LIGHTHOUSE_PRESET=$preset npx lhci upload || status=1
    LIGHTHOUSE_PRESET=$preset npx lhci assert || status=1
done

node tests/lighthouse/summary.mjs | tee lighthouse-results/summary.md
[ -n "${GITHUB_STEP_SUMMARY:-}" ] && cat lighthouse-results/summary.md >> "$GITHUB_STEP_SUMMARY"

exit $status
