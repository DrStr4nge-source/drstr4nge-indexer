# DrStr4nge Indexer – Anleitung (v1.9.0)

Ausführliche Anleitung zu Installation, Einrichtung, Update und Fehlersuche. Die Kurzfassung steht in der [README](../README.md).
Gedacht für einen Server mit **Ubuntu 24.04 LTS** und **PHP 8.5**.

## Schnellinstallation mit install.sh (empfohlen)
Auf einem frischen Ubuntu-24.04-Server:

    git clone https://github.com/DrStr4nge-source/drstr4nge-indexer.git
    cd drstr4nge-indexer
    sudo bash install.sh

(Oder das ZIP eines Releases herunterladen, entpacken und im entpackten Ordner `sudo bash install.sh` ausführen.)

Das Skript fragt nach Domain (oder IP), Admin-E-Mail, ob Manticore installiert werden soll und ob ein
SSL-Zertifikat geholt werden soll. Dann installiert es alles von selbst:

- PHP 8.5 mit PHP-FPM (aus dem bekannten Paketarchiv ondrej/php)
- Composer (mit Prüfsummen-Kontrolle), Node.js 24 LTS (NodeSource)
- MariaDB mit eigener Datenbank und zufälligem Passwort
- Nginx mit fertiger Seiten-Konfiguration, auf Wunsch SSL von Let's Encrypt
- optional Manticore Search
- Laravel + Breeze (Login), die Indexer-Dateien, `.env`, Datenbank-Tabellen, Frontend
- Cronjob für den Zeitplan (Crawler, Prüfung, Metadaten, Aufräumen)
- den ersten Admin; das Passwort wird am Ende angezeigt

Dauer: etwa 5 bis 15 Minuten. Alles wird in `/var/log/usenet-indexer-install.log` mitgeschrieben (nur für root lesbar).
Ziel ist `/var/www/indexer`. Das Skript bricht ab, wenn dort schon etwas liegt, damit nichts überschrieben wird.

Ohne Rückfragen, z.B. für automatische Einrichtung:

    sudo DOMAIN=indexer.example.com ADMIN_EMAIL=ich@example.com \
         INSTALL_MANTICORE=ja INSTALL_SSL=ja NONINTERACTIVE=1 bash install.sh

Weitere Einstellungen per Variable: `APP_DIR` (Zielordner), `DB_NAME`, `DB_USER`, `NODE_MAJOR`.

Nach der Installation noch selbst erledigen:
1. In `/var/www/indexer/.env` eintragen:
   - Usenet-Zugang: `NNTP_HOST`, `NNTP_USER`, `NNTP_PASS`, dazu `NNTP_CONNECTIONS` (höchstens Provider-Limit minus 2)
   - optional `TMDB_API_KEY` (Cover, Inhalt, Genre, Darsteller)
   - optional Zahlung: NOWPayments oder BTCPay (siehe unten)
   - `APP_URL` mit deiner öffentlichen Adresse, falls die Seite aus dem Internet erreichbar ist (nötig für die Zahlungsbestätigung)

   Danach: `cd /var/www/indexer && php artisan config:clear`
2. Einloggen, im Admin-Bereich unter "Newsgroups" Gruppen eintragen. Unter "Einstellungen" Name, Logo und Registrierung festlegen.
3. Verbindung zum Usenet-Provider prüfen: `cd /var/www/indexer && php artisan indexer:test-nntp`
   (zeigt Server, Login und Gruppe und erklärt Fehler). Andere Gruppe testen: `php artisan indexer:test-nntp alt.binaries.xyz`
4. Ersten Crawl starten: `cd /var/www/indexer && sudo -u www-data php artisan indexer:crawl`

Hinweis Laravel-Version: Das Skript nimmt die neueste Laravel-Version. Lässt sich Breeze dort nicht installieren,
nimmt es automatisch Laravel 12 (unterstützt PHP 8.5 ebenfalls).

## Manuelle Installation (Ubuntu 24.04)
Falls du lieber Schritt für Schritt vorgehst. Alle Befehle als root bzw. mit `sudo`.

### 1. Software installieren

    # Basis
    apt update
    apt install -y ca-certificates curl gnupg unzip git software-properties-common acl cron openssl

    # PHP 8.5
    LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
    apt update
    apt install -y php8.5-cli php8.5-fpm php8.5-common php8.5-mysql php8.5-sqlite3 php8.5-mbstring \
        php8.5-xml php8.5-curl php8.5-zip php8.5-gd php8.5-bcmath php8.5-intl php8.5-readline
    php -v      # muss 8.5.x zeigen

    # Composer (offizieller Weg mit Prüfsumme)
    EXPECTED="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    [ "$EXPECTED" = "$(php -r "echo hash_file('sha384', 'composer-setup.php');")" ] && \
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php

    # Node.js 24 LTS
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_24.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list
    apt update && apt install -y nodejs

    # MariaDB und Nginx
    apt install -y mariadb-server nginx

    # Optional: Manticore Search
    curl -fsSLo manticore-repo.noarch.deb https://repo.manticoresearch.com/manticore-repo.noarch.deb
    apt install -y ./manticore-repo.noarch.deb
    apt update && apt install -y manticore      # nur "manticore", NICHT zusätzlich "manticore-extra"
    systemctl enable --now manticore

### 2. Datenbank anlegen

    mysql -e "CREATE DATABASE indexer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
              CREATE USER 'indexer'@'localhost' IDENTIFIED BY 'SICHERES_PASSWORT';
              GRANT ALL PRIVILEGES ON indexer.* TO 'indexer'@'localhost'; FLUSH PRIVILEGES;"

### 3. Laravel, Login und Indexer

    cd /var/www
    composer create-project laravel/laravel indexer
    cd indexer
    composer require laravel/breeze --dev        # klappt das nicht: mit laravel/laravel:^12.0 neu anlegen
    php artisan breeze:install blade

Dann:
1. In der `composer.json` unter `"require"` die Zeile `"php": "^8.5"` eintragen.
2. Die Ordner `app`, `config`, `database`, `resources`, `routes`, `tests` aus diesem Paket in das Projekt kopieren
   (überschreibt u.a. `routes/web.php`, die User-Klasse und das Dashboard; muss **nach** `breeze:install` passieren).
3. In der `.env`: `DB_CONNECTION=mariadb` und die Datenbank-Werte eintragen, dann den Inhalt von `env.additions.txt` anhängen.
   Mit Manticore: `MANTICORE_ENABLED=true`.
4. Zeitplan ans Ende von `routes/console.php`:

       \Illuminate\Support\Facades\Schedule::command('indexer:crawl')->everyTenMinutes()->withoutOverlapping();
       \Illuminate\Support\Facades\Schedule::command('indexer:inspect')->everyFifteenMinutes()->withoutOverlapping();
       \Illuminate\Support\Facades\Schedule::command('indexer:metadata')->everyThirtyMinutes()->withoutOverlapping();
       \Illuminate\Support\Facades\Schedule::command('indexer:cleanup')->dailyAt('04:00');
       \Illuminate\Support\Facades\Schedule::command('indexer:search-index')->weeklyOn(1, '05:00'); // nur mit Manticore

5. Einrichten:

       php artisan migrate --force
       php artisan storage:link
       npm install && npm run build
       php artisan indexer:search-index                     # nur mit Manticore
       php artisan indexer:make-admin deine@mail.de --create  # zeigt das Passwort an
       chown -R www-data:www-data /var/www/indexer

6. Cronjob als Datei `/etc/cron.d/usenet-indexer`:

       * * * * * www-data cd /var/www/indexer && php artisan schedule:run >> /dev/null 2>&1

7. Nginx: Seite mit `root /var/www/indexer/public;` und PHP über `unix:/run/php/php8.5-fpm.sock`.
   Eine fertige Vorlage steht in `install.sh` (Funktion `write_nginx_site`).
8. Optional SSL: `apt install -y certbot python3-certbot-nginx && certbot --nginx -d deine-domain.de`

## Update
Mit einem Befehl, direkt von GitHub (Daten, `.env`, Logo, Cover und Einstellungen aus dem Admin bleiben erhalten):

    cd /tmp && rm -rf dsi && git clone -q https://github.com/DrStr4nge-source/drstr4nge-indexer.git dsi && sudo bash dsi/update.sh

`update.sh` kopiert die neuen Dateien nach `/var/www/indexer`, ergänzt neue Einträge in der `.env` (vorhandene Werte bleiben),
aktualisiert Zeitplan und Datenbank, richtet den Pre-Dienst ein bzw. startet ihn neu und installiert rsync/rclone, falls sie fehlen.
Anderer Ordner: `sudo APP_DIR=/pfad bash dsi/update.sh`.

Achtung: `config/indexer.php` wird überschrieben. Eigene Änderungen dort (z.B. VIP-Preise) danach erneut eintragen.
`install.sh` ist nur für die Erstinstallation und bricht ab, wenn `/var/www/indexer` schon existiert.

Bei Bedarf zusätzlich:

    php artisan storage:link              # falls Logo/Cover nicht angezeigt werden ("already exists" ist ok)
    sudo -u www-data php artisan indexer:recategorize   # bestehende Releases neu einordnen
    sudo -u www-data php artisan indexer:search-index   # nur mit Manticore

## PHP 8.5
Das Projekt ist auf PHP 8.5 ausgelegt. Laravel 13 (die aktuelle Version bei `composer create-project`) unterstützt PHP 8.3 bis 8.5,
Breeze ebenfalls PHP 8.5. Der Code nutzt nichts, was PHP 8.5 als veraltet markiert (geprüft: alte Cast-Namen, Backticks,
`case ...;`, `__sleep`, `imagedestroy`, `curl_close` und weitere). Prüfen: `php -v` muss 8.5.x zeigen.

## Manticore Search (schnelle Suche)
Ohne Manticore sucht der Indexer per `LIKE` in MySQL. Das reicht für wenige hunderttausend Releases.
Mit Manticore läuft die Suche über einen Volltext-Index: schneller, Wortanfänge (`Matr` findet `Matrix`), sortiert nach Datum.

1. Manticore installieren: macht `install.sh` automatisch. Von Hand siehe "Software installieren" oben,
   oder mit Docker: `docker compose -f docker-compose.manticore.yml up -d` (lauscht nur lokal auf Port 9306).
2. In der `.env`: `MANTICORE_ENABLED=true` (Host, Port, Tabellenname bei Bedarf anpassen).
3. Index anlegen und füllen: `php artisan indexer:search-index` (mit `--fresh` vorher leeren).

Danach hält sich der Index selbst aktuell: jedes gespeicherte oder gelöschte Release wird übertragen.
Nur Releases, die User sehen dürfen, kommen in den Index. Ist Manticore nicht erreichbar, sucht die Seite automatisch
wieder per MySQL und der Crawler läuft weiter (Warnung im Log). Danach einmal `indexer:search-index` ausführen.
Suchen über IMDb-, TMDB- oder TVDB-ID laufen immer über MySQL, das ist dafür schnell genug.
Blättern geht bis 10.000 Treffer tief (`MANTICORE_MAX_RESULTS`).

## TMDB, IMDb und Cover
1. Kostenlosen API-Key auf themoviedb.org erstellen (Konto, Einstellungen, API) und als `TMDB_API_KEY` in die `.env` eintragen.
2. Einmalig: `php artisan storage:link` (damit die Cover-Dateien über `/storage/covers/...` erreichbar sind).
3. PHP-Erweiterung GD mit WebP muss aktiv sein (`php -r "var_dump(function_exists('imagewebp'));"` muss `true` zeigen).
4. `php artisan indexer:metadata` verknüpft Film- und Serien-Releases mit TMDB (läuft automatisch alle 30 Minuten, 200 Releases pro Lauf).
   Mit `--retry` werden bisher nicht gefundene erneut versucht. Genre, Regie/Erfinder und Darsteller werden mitgeladen,
   bei schon bekannten Titeln automatisch nachgeholt (50 pro Lauf, `--details=...`).

Ablauf: Titel und Jahr werden aus dem Release-Namen gelesen, beim Anbieter gesucht (nur Treffer mit passendem Titel und Jahr werden
übernommen), IMDb- und TVDB-ID, Genre, Regie und Darsteller von TMDB geholt (Sprache: `TMDB_LANGUAGE`, Standard de-DE) und das Poster als WebP (Breite 342, Qualität 80) gespeichert.
Gleiche Titel werden nur einmal abgefragt.

- **IMDb:** IMDb hat keine freie API. Du bekommst die IMDb-ID und den Link zur IMDb-Seite (über TMDB), aber keine IMDb-Bewertung.
  Angezeigt wird die TMDB-Bewertung.
- **Sonarr/Radarr:** Die Newznab-API unterstützt jetzt `imdbid`, `tmdbid` und `tvdbid`, die Treffer enthalten die IDs und eine `coverurl`.
- **Lizenz:** Die TMDB-API ist nur für nicht-kommerzielle Nutzung frei. Du verkaufst VIP-Zugang, das gilt wahrscheinlich als kommerziell.
  Dann brauchst du eine Vereinbarung mit TMDB. Der TMDB-Hinweis steht schon in der Browse-Ansicht, TMDB verlangt zusätzlich ihr Logo.

### Metadaten-Quelle wechseln
TMDB ist nur ein austauschbarer Anbieter. Die Quelle wählst du mit `METADATA_PROVIDER` in der `.env`:
- `tmdb`: TMDB (Standard, braucht `TMDB_API_KEY`)
- `none`: keine Metadaten, keine Cover (die Seite funktioniert ohne)

Eigene Quelle einbauen (z.B. TVmaze):
1. Klasse in `app/Services/` anlegen, die `MetadataProvider` umsetzt: `name()`, `enabled()` und `find(array $parsed)`.
2. In `config/indexer.php` unter `metadata.providers` eintragen, z.B. `'tvmaze' => \App\Services\Tvmaze::class`.
3. `METADATA_PROVIDER=tvmaze` setzen.

`find()` bekommt Typ, Titel und Jahr (aus dem Release-Namen) und liefert ein Array oder `null`. Pflichtfelder: `source`, `source_id`, `type`, `title`.
Optional: `year`, `overview`, `rating`, `poster_url` (volle URL), `imdb_id`, `tmdb_id`, `tvdb_id`. Bei Netz- oder Limit-Fehlern eine
`\RuntimeException` werfen, dann versucht der Befehl es später erneut. Das Beispiel ist `app/Services/Tmdb.php`.
Cover werden immer lokal als WebP gespeichert. Erlaubt eine Quelle das nicht (TVmaze will Verlinkung statt Kopien), dann `poster_url`
weglassen oder die Cover-Speicherung in `FetchMetadata` für diese Quelle abschalten.

## Pre-Daten (IRC und predb.net)
Pre-Daten zeigen, wann ein Release in der Szene erschienen ist und ob es genuked wurde. Der Indexer verknüpft sie automatisch mit
Releases gleichen Namens. In den Listen steht dann z.B. "Pre vor 3 Std", genukte Releases bekommen ein rotes Schild. Im Admin unter
**PreDB** siehst du alle Einträge und ob der Scraper verbunden ist.

Es gibt drei Quellen, alle sind einzeln abschaltbar:

**1. IRC** (Kanäle `#PreNNTmux` und `#nZEDbPRE` auf synIRC, gleiche Einträge wie bei NNTmux). In der `.env`:

    SCRAPE_IRC_SERVER=irc.synirc.net
    SCRAPE_IRC_PORT=6697
    SCRAPE_IRC_TLS=true
    SCRAPE_IRC_USERNAME=EinEindeutigerNick
    SCRAPE_IRC_REALNAME=Dein Name
    SCRAPE_IRC_CHANNELS="#PreNNTmux,#nZEDbPRE"

Einfacher geht es im Admin unter **PreDB > IRC-Einstellungen**: Server, Port, TLS, Nick, Kanäle und ein- oder ausschalten. Werte
aus dem Admin haben Vorrang vor der `.env`, der Dienst übernimmt sie nach wenigen Sekunden ohne Neustart. "Auf .env zurücksetzen"
löscht die Admin-Werte wieder. So kannst du tote Kanäle austauschen, ohne an den Server zu müssen.

Wichtig in der `.env`: Kanäle in Anführungszeichen, sonst gilt `#` als Kommentar. Der Nick muss eindeutig sein und normal aussehen, sonst droht ein
Bann im Kanal. Der Scraper läuft als Dienst `drstr4nge-pre` dauerhaft im Hintergrund und verbindet sich nach Abbrüchen selbst neu.
Nach einer Änderung an der `.env`:

    php artisan config:clear
    systemctl restart drstr4nge-pre
    journalctl -u drstr4nge-pre -f          # Ausgabe live ansehen (Strg+C beendet nur die Anzeige)

Zum Testen ohne Dienst, mit jeder Kanalzeile: `sudo -u www-data php artisan indexer:pre-irc --debug`

**2. predb.net** (API, kein Konto nötig). Läuft alle 5 Minuten über den Zeitplan und holt die neuesten Einträge. Zusätzlich schlägt
er pro Lauf bis zu `PREDB_API_LOOKUP` (Standard 20) neue Releases ohne Pre einzeln nach. Abschalten mit `PREDB_API_ENABLED=false`.
Von Hand: `sudo -u www-data php artisan indexer:pre-fetch`

**3. RSS-Feeds**, Standard `https://predb.me/?rss=1`. Laufen mit `indexer:pre-fetch` alle 5 Minuten mit. Weitere Feeds mit Komma in
`PRE_RSS_FEEDS` eintragen, leer lassen schaltet RSS ab. RSS liefert nur Name, Zeit und Kategorie, keine Größe und keine Nukes.

**4. Alte PreDB-Dumps einspielen** (einmalig). Die Tages-Dumps von nZEDb ([github.com/nZEDb/nZEDbPre_Dumps](https://github.com/nZEDb/nZEDbPre_Dumps),
2014 bis Juni 2024, auch deutsche P2P-Releases von xrel) lädt der Indexer selbst:

    cd /var/www/indexer
    sudo -u www-data php artisan indexer:pre-import --nzedb --since=2023-01-01

Alle Dumps sind etwa 7 Millionen Einträge und belegen grob 2 GB in der Datenbank, die Sicherung wird entsprechend größer.
Mit `--since` holst du nur Pres ab einem Datum. Ohne Grund lohnt der ganze Bestand selten, weil nur Releases verknüpft werden,
die auch in deinen Gruppen auftauchen. Vorhandene Einträge bleiben unverändert, mehrfaches Einspielen schadet nicht. Danach
werden die Releases automatisch verknüpft. Bei großen Mengen am besten in `screen` oder mit `nohup ... &` starten.
Eigene Dateien gehen auch: `indexer:pre-import /pfad/datei.csv.gz` (nZEDb-Format oder CSV mit Kopfzeile, Spalten z.B.
`title;predate;size;files;category`), ein Ordner mit Dumps oder eine URL.

Vorhandene Releases nachträglich verknüpfen (z.B. nach dem ersten Tag): `sudo -u www-data php artisan indexer:pre-match`

**Von Hand nachrüsten** (nur nötig ohne `update.sh`; `install.sh` und `update.sh` machen das selbst):

    cd /var/www/indexer && php artisan migrate --force
    echo "\Illuminate\Support\Facades\Schedule::command('indexer:pre-fetch')->everyFiveMinutes()->withoutOverlapping();" >> routes/console.php
    cat > /etc/systemd/system/drstr4nge-pre.service <<'UNIT'
    [Unit]
    Description=DrStr4nge Indexer: Pre-Daten aus IRC
    After=network-online.target mariadb.service
    Wants=network-online.target

    [Service]
    User=www-data
    WorkingDirectory=/var/www/indexer
    ExecStart=/usr/bin/php /var/www/indexer/artisan indexer:pre-irc
    Restart=on-failure
    RestartSec=60

    [Install]
    WantedBy=multi-user.target
    UNIT
    systemctl daemon-reload && systemctl enable --now drstr4nge-pre

## Zugangsdaten im Admin
Unter **Admin > Zugangsdaten** trägst du Usenet-Zugang, TMDB-Key, Mail-Versand (SMTP), Warn-Empfänger, NOWPayments und die
Sicherung ein, ohne die `.env` anzufassen. Werte aus dem Admin gelten sofort und haben Vorrang. Ein leeres Feld bedeutet: Wert aus
der `.env`. Passwörter werden mit dem `APP_KEY` verschlüsselt gespeichert und nie wieder angezeigt. Knöpfe zum Testen gibt es für
Usenet ("Verbindung testen") und Mail ("Test-Mail schicken").

## Sicherung (auch nach Google Drive)
Jede Nacht um 03:30 Uhr (`BACKUP_TIME`) sichert `indexer:backup` die Datenbank (gzip), die `.env` und das Logo in eine Datei
`storage/app/backups/drstr4nge-backup-DATUM.tar`. Aufbewahrt werden 7 Tage (`BACKUP_KEEP_DAYS`). Von Hand:
`sudo -u www-data php artisan indexer:backup` oder im Admin unter Zugangsdaten > Sicherung "Jetzt sichern".

**Google Drive:** Admin > Zugangsdaten > Sicherung > Google Drive > Verbinden. Dafür auf deinem PC rclone herunterladen
(https://rclone.org/downloads/), dort `rclone authorize "drive"` ausführen, bei Google anmelden und den angezeigten Text in das Feld
einfügen. Ab dann landet jede Sicherung zusätzlich im gewählten Drive-Ordner. Dort werden alte Sicherungen ebenfalls nach
`BACKUP_KEEP_DAYS` gelöscht, endgültig und ohne Umweg über den Papierkorb. Andere Dateien im Ordner bleiben unberührt. Alternativ auf dem Server: `sudo bash setup-gdrive.sh`. Andere Ziele (Dropbox, OneDrive, S3) gehen
mit rclone genauso (`BACKUP_RCLONE_REMOTE`). Ein zweiter Server per SSH: `BACKUP_REMOTE=backup@server2:/srv/backups/`.

**Wiederherstellen:**

    tar -xf drstr4nge-backup-DATUM.tar                    # ergibt datenbank.sql.gz, env, branding/
    gunzip -c datenbank.sql.gz | mysql -u root indexer    # Datenbank zurückspielen
    cp env /var/www/indexer/.env                          # nur bei neuem Server (enthält den APP_KEY)

## Überwachung, Warn-Mails und Log
`indexer:health` prüft alle 15 Minuten: Findet der Crawler seit 6 Stunden nichts (`ALERT_CRAWL_HOURS`)? Ist der IRC-Scraper seit
30 Minuten getrennt? Ist die Festplatte zu 90 % voll (`ALERT_DISK_PERCENT`)? Fehlt die Sicherung oder ist sie fehlgeschlagen?
Dann geht eine Mail an `ALERT_EMAIL` bzw. alle Admins, die gleiche Warnung höchstens alle 12 Stunden, und eine Mail, sobald es
wieder läuft. Test: `php artisan indexer:health --test`.

Im Admin unter **Log** siehst du Warnungen und Fehler aus `storage/logs/laravel.log` (aufklappbar mit allen Details), dazu die
aktuellen Probleme der Überwachung. Die Übersicht zeigt oben eine rote Box, solange etwas nicht stimmt.

**Was läuft gerade?** `php artisan indexer:status` zeigt laufende Crawls, Stand jeder Gruppe, Releases, Pre-Daten, Sicherung und die
letzten Läufe aller geplanten Befehle. Ein Crawl von Hand startet nicht, solange schon einer läuft. Nach einem Absturz hängende
Sperren löst `php artisan indexer:crawl --unlock`.

## Zwei-Faktor-Anmeldung für Admins
Admin > Zwei-Faktor > Einrichten: QR-Code mit einer Code-App scannen (Google Authenticator, Aegis, 2FAS, Microsoft Authenticator)
und den Code eingeben. Danach fragt der Admin-Bereich nach jedem Login einmal nach dem 6-stelligen Code. Du bekommst 8
Wiederherstellungscodes für den Notfall. Unter Einstellungen kannst du Zwei-Faktor für alle Admins verlangen.
Handy und Codes verloren: `php artisan indexer:2fa-reset deine@adresse`.

## Krypto-Zahlung mit NOWPayments (ohne eigenen Server, z.B. Monero)
1. Konto bei nowpayments.io anlegen, unter Einstellungen die Auszahlungs-Wallet(s) eintragen (z.B. deine Monero-Adresse).
2. Im NOWPayments-Dashboard einen API-Key und ein IPN-Secret erzeugen.
3. In der `.env`:

       PAYMENT_PROVIDER=nowpayments
       NOWPAYMENTS_API_KEY=dein-api-key
       NOWPAYMENTS_IPN_SECRET=dein-ipn-secret
       NOWPAYMENTS_PAY_CURRENCY=xmr     # nur Monero anbieten; leer lassen = Kunde wählt die Kryptowährung

   Danach `php artisan config:clear`.
4. Die Seite muss aus dem Internet erreichbar sein (`APP_URL` mit echter Domain), sonst kommt die Zahlungsbestätigung
   nicht an. Die Rückmeldung geht an `https://deine-domain/nowpayments/ipn` und wird per Signatur geprüft.
5. VIP wird freigeschaltet, wenn NOWPayments den Status "finished" meldet (Geld ist bei dir angekommen). Teilzahlungen schalten nicht frei.
   Zum Ausprobieren ohne echtes Geld: `NOWPAYMENTS_SANDBOX=true` mit einem Sandbox-Konto.

## Krypto-Zahlung (BTCPay Server)
1. BTCPay Server betreiben (selbst gehostet) und einen Store anlegen.
2. API-Key erzeugen (Rechte: Rechnungen erstellen/ansehen) und in die `.env` eintragen.
3. Webhook im Store anlegen: URL `https://deine-domain/btcpay/webhook`, Event "Invoice settled".
   Das Secret in `BTCPAY_WEBHOOK_SECRET` eintragen.
4. Preise und Laufzeiten stehen in `config/indexer.php`.

## Was drin ist
- **Verbindungstest** (`indexer:test-nntp`): prüft Server, Login, Gruppe und Header-Abruf und erklärt Fehler auf Deutsch.
- **Mehrere Verbindungen** (`NNTP_CONNECTIONS`, Standard 4): der Crawler arbeitet so viele Newsgroups gleichzeitig ab, jede mit eigener Verbindung. Eine einzelne Gruppe läuft immer über eine Verbindung. Einstellen auf höchstens Provider-Limit minus 2. Einmalig anders: `php artisan indexer:crawl --connections=8`, nur eine Gruppe: `--group=alt.binaries.xyz`.
- **Crawler** (XOVER): baut Releases aus den Betreffzeilen, rechnet fehlende Dateien in die Vollständigkeit ein. Versteht klassische
  (`Name [01/20] - "datei.rar" yEnc (1/50)`) und abweichende Formate (`(7/8) - Description - "datei.rar" - 318 MB - yEnc (4/11)`):
  steht im Betreff nur ein Platzhalter, kommt der Name aus dem Dateinamen. Umlaute in Latin-1 werden nach UTF-8 umgewandelt.
  Wie weit der erste Crawl einer Gruppe zurückgeht: `INDEXER_BACKFILL_DAYS=60` (Tage, der Indexer sucht für jede Gruppe selbst
  den passenden Artikel) oder, wenn 0, `INDEXER_BACKFILL` (Anzahl der neuesten Artikel).
  Eine fehlerhafte Gruppe stoppt die anderen nicht. Nach einem Update kannst du eine Gruppe im Admin mit "Neu einlesen" noch einmal lesen lassen.
- **Kategorien** (Newznab): Film SD/HD/UHD/BluRay, TV SD/HD/UHD/Sport/Anime, Audio, Bücher, Comics, PC, Spiele, Konsolen, XXX. Sonarr und Radarr finden dadurch passende Releases. Anpassbar in `config/indexer.php` und `app/Services/Categorizer.php`.
- **Verschleierte Namen:** zufällige Namen werden erkannt, versteckt und (`indexer:inspect`) über die PAR2-Datei aufgelöst.
  Bleibt der Name auch in der PAR2-Datei verschleiert, vergleicht `indexer:pre-deobfuscate` (alle 30 Minuten) die echte Größe und
  Dateianzahl aus der PAR2-Datei mit der PreDB. Passt genau ein Pre (Größe, Dateien, Zeitfenster), bekommt das Release dessen Namen.
  Abschalten: `PRE_DEOBFUSCATE=false`. Erst testen: `php artisan indexer:pre-deobfuscate --dry-run`.
- **Passwort-Erkennung:** RAR4/RAR5-Header werden geprüft (Datei- und Header-Verschlüsselung), dazu Namens-Hinweise.
- **Spam:** Blacklist (Poster mit * und Name per Regex, im Admin), Spam-Wörter, nur-ausführbare-Dateien, zu kleine Releases.
  Namens-Regeln greifen erst, wenn der echte Name bekannt ist (nicht bei verschleierten Zufallsnamen).
  Beispiel "nur deutsche Releases": Typ "Name (Regex)" mit `^(?!.*(german|deutsch|(?<![a-z])ger(?![a-z])))`.
  Neue oder gelöschte Regeln gelten nach spätestens 10 Minuten auch für alle vorhandenen Releases.
- **Aufräumen** (`indexer:cleanup`, täglich 4 Uhr): alte Releases (Standard 1000 Tage), nie fertig gewordene (2 Tage), Spam (7 Tage), verschleierte ohne Namen (`INDEXER_DELETE_OBFUSCATED_DAYS`, Standard 3), passwortgeschützte (`INDEXER_DELETE_PASSWORDED_DAYS`, Standard 1), alte Download-Logs, offene Zahlungen. Von Hand freigegebene Releases bleiben. 0 schaltet eine Regel ab.
- **Rate-Limit:** API pro User und Minute (Standard 60, VIP 300) plus pro IP.
- **Newznab-API:** `caps`, `search`, `tvsearch` (season/ep), `movie`, `get`, mit `cat`, `maxage`, `offset`, `limit`, `imdbid`, `tmdbid`, `tvdbid`, `tvmazeid`.
- **TVmaze** (`indexer:tvmaze`, stündlich, ohne Key): ergänzt bei Serien TVDB-, IMDb- und TVmaze-ID, damit Sonarr Folgen zuverlässiger findet. Abschalten: `TVMAZE_ENABLED=false`.
- **Merkliste und RSS:** Filme und Serien auf der Titelseite merken (`/merkliste`). Auf der Konto-Seite stehen RSS-Feeds für neue
  Releases, nur die Merkliste, nur Filme oder nur Serien, z.B. für NZBGet oder SABnzbd: `/rss?apikey=KEY&t=merkliste` bzw. `&cat=2000`.
- **Melden:** Neben jedem Download-Knopf meldet "!" ein Release als defekt, mit Passwort, falsch benannt oder Spam. Im Admin unter
  Meldungen: erledigen, als Spam markieren oder löschen.
  In Sonarr/Radarr: Typ Newznab, URL = Adresse der Seite, API-Pfad `/api`, API-Key von der Konto-Seite, Kategorie TV (5000) bzw. Movies (2000).
- **Design:** dunkles Theme mit orangem Akzent. Startseite mit Cover-Reihen (neue Filme, neue Serien) und den neuesten Releases,
  Detailseite pro Film/Serie (`/titel/...`) mit Cover, Bewertung, Inhalt, IMDb/TMDB-Links und allen Releases. Suche oben in der Leiste
  (Strg+K springt hinein). Läuft auch auf dem Handy. Die Farben stehen zentral in `resources/views/layouts/theme.blade.php` (oben bei `:root`).
- **Filme und Serien** (`/filme`, `/serien`): Karten mit Cover, Jahr, Bewertung, IMDb/TMDB-Link, Inhalt, Genre, Regie (bei Serien: Erfinder), Darstellern und den verfügbaren Releases mit Download. Suche nach Titel und Filter nach Genre. Die Daten kommen von TMDB (`indexer:metadata`, läuft alle 30 Minuten; bereits bekannte Titel bekommen Genre/Regie/Darsteller automatisch nachgeladen, 50 pro Lauf).
- **Registrierung:** Link auf der Login-Seite. Im Admin unter Einstellungen: offen für alle, nur mit Einladungscode oder geschlossen. Codes unter Admin > Einladungen erzeugen (auch als fertiger Link zum Kopieren). Schutz: 5 Registrierungen pro Minute und IP, unsichtbare Bot-Falle.
- **User:** Konto-Seite (`/konto`) mit API-Key, Tageslimit, Download-Verlauf; VIP per NOWPayments oder BTCPay (`PAYMENT_PROVIDER`).
- **Admin:**
  - Übersicht mit Zahlen, Betrieb (Crawler, Sicherung, Pre-Daten, Meldungen) und Server-Auslastung
  - User: VIP vergeben/entfernen, sperren, Admin machen
  - Newsgroups: hinzufügen, pausieren, "Neu einlesen", löschen
  - Releases: Filter (auffällig, verschleiert, Spam, Passwort, unvollständig), Suche nach Name/Poster, Mehrfachauswahl
    (Umschalt+Klick für Bereiche) mit Freigeben, Spam, Löschen, "Poster sperren"; auch "auf alle Treffer anwenden"
  - PreDB, Meldungen, Blacklist, Zahlungen, Einladungen, Log
  - Zugangsdaten: Usenet, TMDB, Mail, Warnungen, Zahlung, Sicherung/Google Drive
  - Einstellungen: Name der Seite, Logo-Upload (PNG, JPG oder WebP, höchstens 1 MB), Registrierung, Zwei-Faktor-Pflicht
  - Zwei-Faktor-Anmeldung
- **Befehle:** `indexer:crawl`, `indexer:status`, `indexer:inspect`, `indexer:metadata`, `indexer:tvmaze`, `indexer:cleanup`, `indexer:recategorize`,
  `indexer:search-index`, `indexer:test-nntp`, `indexer:backup`, `indexer:health`, `indexer:pre-irc`, `indexer:pre-fetch`, `indexer:pre-match`,
  `indexer:pre-deobfuscate`, `indexer:pre-import`, `indexer:repair-split`, `indexer:2fa-reset`, `indexer:make-admin mail@beispiel.de --create`.
- **Zeit:** gespeichert wird in UTC, angezeigt und geplant in deutscher Zeit (`INDEXER_TIMEZONE=Europe/Berlin`).
- **Zeitplan:** steht in `routes/indexer-schedule.php` und kommt mit jedem Update mit.
- **Tests:** `php artisan test` (Unit- und Feature-Tests).

## Grenzen
- Verschleierte Releases ohne PAR2-Datei bleiben versteckt. Im Admin unter Releases, Filter "Verschleiert", kannst du sie freigeben.
- Passwort-Erkennung findet nur, was im RAR-Header steht. Passwörter in 7z/ZIP-Archiven werden nicht erkannt.
- Metadaten gibt es nur für Filme und Serien, und nur wenn Titel und Jahr im Namen stehen. Anime-Namen werden schlechter erkannt.
- Ohne Manticore wird die LIKE-Suche bei sehr vielen Releases (Millionen) langsam. Dann Manticore einschalten.
- Kein Mail-Versand eingerichtet: "Passwort vergessen" und Bestätigungs-Mails funktionieren erst mit SMTP-Daten in der `.env`.
- Sonarr/Radarr finden nur etwas, wenn Newsgroups mit Serien bzw. Filmen gecrawlt werden.

## Fehlersuche
- **Fehler 500 oder weiße Seite:** `grep -E "\.(ERROR|CRITICAL)" /var/www/indexer/storage/logs/laravel.log | tail -n 3 | cut -c1-600`
- **Verbindung zum Usenet:** `php artisan indexer:test-nntp` erklärt Login-, Port- und SSL-Fehler.
- **Neues Design oder neue Funktion nicht sichtbar:** Dateien wurden nicht kopiert. Prüfen mit
  `grep -c tm-body /var/www/indexer/resources/views/layouts/app.blade.php` (muss 1 sein), dann Update-Befehle wiederholen und Strg+F5.
- **Logo oder Cover fehlen:** `php artisan storage:link`. Die Einstellungsseite warnt, wenn die Verknüpfung fehlt.
- **Läuft der Zeitplan?** `php artisan schedule:list` und `cat /etc/cron.d/usenet-indexer`.
- **Was liegt im Index?** `php artisan tinker --execute='echo App\Models\Release::count(), " gesamt, ", App\Models\Release::visible()->count(), " sichtbar\n";'`
- **Wenig Releases nach dem Crawl:** ein paar Betreffzeilen der Gruppe ansehen:
  `php artisan tinker --execute='$n = app(App\Services\Nntp::class); $n->connect(); [$l, $h] = $n->group("a.b.xyz"); foreach ($n->xover($h - 40, $h) as $r) echo $r[1], "\n"; $n->close();'`

## Teststand
- Installation mit `install.sh` auf Ubuntu 24.04 und Betrieb (Design, Logo, VIP-Vergabe, mehrere Verbindungen, TMDB-Metadaten,
  NOWPayments) im Praxiseinsatz geprüft.
- Ausgeführt und bestanden (PHP 8.3, ohne Laravel): Kategorien, Verschleierung, Spam-Klassifizierung, yEnc, PAR2, RAR-Prüfung,
  Betreff-Parser (mit echten Betreffzeilen), Release-Bau, Titel-Parser, WebP-Umwandlung, TMDB-Logik (nachgestellte Antworten),
  Manticore-Client und Index-Regeln, Prozess-Pool für parallele Verbindungen, NNTP-Client, Verbindungstest und Crawler gegen einen
  nachgestellten Usenet-Server, NOWPayments-Signatur gegen eine unabhängige Berechnung.
- Laravel-Feature-Tests (Seiten, Admin, Registrierung, Zahlungen, Suche, Aufräumen) liegen in `tests/Feature`: `php artisan test`.
