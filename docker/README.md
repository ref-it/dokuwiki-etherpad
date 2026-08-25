# Lokale Testumgebung (DokuWiki + Etherpad)

```
docker compose up -d --build
```

Startet:
- **etherpad** (`etherpad/etherpad:latest`, klassische API-Key-Auth, Key liegt fest in `docker/etherpad/APIKEY.txt`) auf `http://localhost:9001`
- **dokuwiki** (frisches DokuWiki, ACL deaktiviert - reiner Testzweck, kein Login nötig) auf `http://localhost:8089`, das Plugin-Verzeichnis (dieses Repo) ist read-only nach `lib/plugins/etherpadlite/` gemountet

Beide Container laufen mit `network_mode: host`, damit `etherpadlite_url` (`http://localhost:9001`) sowohl vom Browser als auch von PHPs curl-Aufrufen aus identisch erreichbar ist.

Seite anlegen zum Testen, z. B.:
```
docker compose exec dokuwiki sh -c "echo 'Testinhalt' > /var/www/html/data/pages/test.txt && chown www-data:www-data /var/www/html/data/pages/test.txt"
```
dann im Browser: `http://localhost:8089/doku.php?id=test&do=edit`

Logs:
```
docker compose logs -f dokuwiki   # Apache/PHP
docker compose logs -f etherpad   # Etherpad-Server
```

Alles zurücksetzen (inkl. Wiki-Inhalte/Config):
```
docker compose down -v
```

## Bekannte Stolpersteine

- Die Etherpad-`APIKEY.txt` liegt bei `/opt/etherpad-lite/APIKEY.txt` - **außerhalb** des `var/`-Volumes. Deshalb wird sie hier über `docker/etherpad/APIKEY.txt` fest hineingemountet, sonst würde bei jeder Container-Neuerstellung ein neuer Key generiert.
- Dieses Etherpad-Image nutzt standardmäßig `authenticationMethod: sso`, nicht die klassische API-Key-Auth, die der PHP-Client des Plugins spricht - daher `AUTHENTICATION_METHOD=apikey` in `docker-compose.yml`.
- `externals/etherpad-lite-client` ist ein Git-Submodul und muss lokal ausgecheckt sein (`git submodule update --init`), sonst fehlt die Client-Klasse im Mount.
