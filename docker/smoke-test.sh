#!/bin/bash
# Exercises the plugin's real AJAX endpoints (pad_open -> pad_close) against
# the docker-compose test stack (docker-compose.yml) and fails loudly if
# anything regresses. Run locally after `docker compose up -d --build`, or
# from the "test" GitHub Actions job.
set -euo pipefail

BASE="http://localhost:8089"
ETHERPAD_BASE="http://localhost:9001"
PAGE="smoketest"
COOKIES="$(mktemp)"
trap 'rm -f "$COOKIES"' EXIT

fail() {
    echo "FAIL: $1" >&2
    exit 1
}

wait_for() {
    local url="$1" label="$2"
    echo "==> waiting for ${label}..."
    for _ in $(seq 1 60); do
        if curl -sf -o /dev/null "$url"; then
            return 0
        fi
        sleep 2
    done
    fail "${label} did not become ready at ${url}"
}

wait_for "${BASE}/doku.php" "dokuwiki"
wait_for "${ETHERPAD_BASE}/" "etherpad"

echo "==> seeding test page..."
docker compose exec -T dokuwiki sh -c "
    mkdir -p /var/www/html/data/pages &&
    echo 'CI smoke test content' > /var/www/html/data/pages/${PAGE}.txt &&
    chown www-data:www-data /var/www/html/data/pages/${PAGE}.txt
"

echo "==> priming session..."
curl -sf -c "$COOKIES" -b "$COOKIES" "${BASE}/doku.php?id=${PAGE}&do=edit" -o /dev/null

echo "==> pad_open..."
OPEN_RESP=$(curl -sf -c "$COOKIES" -b "$COOKIES" -X POST "${BASE}/lib/exe/ajax.php" \
    --data-urlencode "call=pad_open" \
    --data-urlencode "id=${PAGE}" \
    --data-urlencode "rev=" \
    --data-urlencode "sectok=" \
    --data-urlencode "isSaveable=true")
echo "$OPEN_RESP"
echo "$OPEN_RESP" | jq -e '.error == null' >/dev/null || fail "pad_open returned an error"
echo "$OPEN_RESP" | jq -e '.isOwner == true' >/dev/null || fail "pad_open did not report ownership"
echo "$OPEN_RESP" | jq -e '.url | test("^http://localhost:9001/p/")' >/dev/null || fail "pad_open returned an unexpected pad url"

echo "==> has_pad (second request, same session - checks owner identity is stable across requests)..."
HAS_RESP=$(curl -sf -c "$COOKIES" -b "$COOKIES" -X POST "${BASE}/lib/exe/ajax.php" \
    --data-urlencode "call=has_pad" \
    --data-urlencode "id=${PAGE}" \
    --data-urlencode "rev=" \
    --data-urlencode "isSaveable=true")
echo "$HAS_RESP"
echo "$HAS_RESP" | jq -e '.exists == true' >/dev/null || fail "has_pad lost track of the pad (session/ownership regression)"

echo "==> pad_close..."
CLOSE_RESP=$(curl -sf -c "$COOKIES" -b "$COOKIES" -X POST "${BASE}/lib/exe/ajax.php" \
    --data-urlencode "call=pad_close" \
    --data-urlencode "id=${PAGE}" \
    --data-urlencode "rev=" \
    --data-urlencode "sectok=" \
    --data-urlencode "isSaveable=true" \
    --data-urlencode "prefix=" \
    --data-urlencode "suffix=" \
    --data-urlencode "date=0")
echo "$CLOSE_RESP"
echo "$CLOSE_RESP" | jq -e '.status == "OK"' >/dev/null || fail "pad_close did not report success"

echo "==> checking etherpad log for known regressions..."
if docker compose logs etherpad 2>/dev/null | grep -qi "no author for authorid"; then
    fail "etherpad log shows the a.etherpad-system placeholder-author regression"
fi
if docker compose logs etherpad 2>/dev/null | grep -qi "no or wrong api key"; then
    fail "etherpad log shows an API-key rejection (arg_separator.output / http_build_query regression)"
fi

echo "ALL CHECKS PASSED"
