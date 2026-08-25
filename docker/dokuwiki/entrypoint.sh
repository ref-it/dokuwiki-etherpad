#!/bin/bash
set -euo pipefail

DATA_DIR=/var/www/html/data
CONF_DIR=/var/www/html/conf

mkdir -p "$DATA_DIR" "$CONF_DIR"

# First run: data/ and conf/ are empty (they're mounted as named volumes),
# so seed them from the DokuWiki tarball baked into the image.
if [ ! -f "$DATA_DIR/.bootstrapped" ]; then
    echo "[entrypoint] seeding data/ from image defaults"
    cp -rn /var/www/html-defaults/data/. "$DATA_DIR/"
    touch "$DATA_DIR/.bootstrapped"
fi

if [ ! -f "$CONF_DIR/local.php" ]; then
    echo "[entrypoint] writing conf/local.php (ACL disabled - local test env only)"
    APIKEY="changeme"
    if [ -n "${ETHERPAD_APIKEY_FILE:-}" ] && [ -f "$ETHERPAD_APIKEY_FILE" ]; then
        APIKEY=$(cat "$ETHERPAD_APIKEY_FILE")
    fi
    cat > "$CONF_DIR/local.php" <<PHP
<?php
\$conf['title']       = 'Etherpad Test Wiki';
\$conf['lang']        = 'de';
\$conf['useacl']      = 0;
\$conf['autopasswd']  = 0;

\$conf['plugin']['etherpadlite']['etherpadlite_url']     = '${ETHERPAD_URL:-http://localhost:9001}';
\$conf['plugin']['etherpadlite']['etherpadlite_apikey']  = '${APIKEY}';
\$conf['plugin']['etherpadlite']['etherpadlite_group']   = 'testwiki';
\$conf['plugin']['etherpadlite']['etherpadlite_domain']  = 'localhost';
\$conf['plugin']['etherpadlite']['etherpadlite_urlargs'] = '';
PHP
fi

if [ ! -f "$CONF_DIR/local.protected.php" ]; then
    cat > "$CONF_DIR/local.protected.php" <<'PHP'
<?php
$conf['savedir'] = '/var/www/html/data';
PHP
fi

chown -R www-data:www-data "$DATA_DIR" "$CONF_DIR"

exec "$@"
