#!/usr/bin/env bash
# Lighthouse budget (MA-267): the shop from tests/e2e/serve.sh, then lighthouserc.cjs on phone and desktop.
# Both presets always run; the script fails when either misses a threshold. Reports land in lighthouse-results/.
set -uo pipefail

cd "$(dirname "$0")/../.."

: "${E2E_PORT:=8123}"
export E2E_PORT
# The shop needs a panel account; nobody logs in here, so a random password is enough.
: "${E2E_ADMIN_PASSWORD:=$(openssl rand -hex 16)}"
export E2E_ADMIN_PASSWORD
# php artisan serve handles requests one at a time without this; the browser loads the assets in parallel.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

rm -rf lighthouse-results .lighthouseci
mkdir -p lighthouse-results

bash tests/e2e/serve.sh > lighthouse-results/server.log 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null; pkill -P "$server" 2>/dev/null' EXIT

for _ in $(seq 1 120); do
    curl -fsS "http://127.0.0.1:${E2E_PORT}/up" > /dev/null 2>&1 && break
    kill -0 "$server" 2>/dev/null || { cat lighthouse-results/server.log; exit 1; }
    sleep 1
done

status=0
for preset in mobile desktop; do
    rm -rf .lighthouseci
    LHCI_PRESET=$preset npx lhci collect || status=1
    LHCI_PRESET=$preset npx lhci upload || status=1
    LHCI_PRESET=$preset npx lhci assert || status=1
done

node tests/lighthouse/summary.mjs | tee lighthouse-results/summary.md
[ -n "${GITHUB_STEP_SUMMARY:-}" ] && cat lighthouse-results/summary.md >> "$GITHUB_STEP_SUMMARY"

exit $status
