#!/usr/bin/env bash
# =============================================================================
#  DrStr4nge Indexer - Installer für Ubuntu 24.04 LTS
#
#  Aufruf (im entpackten Ordner):   sudo bash install.sh
#
#  Installiert: PHP 8.5 (+FPM), Composer, Node.js, MariaDB, Nginx, optional
#  Manticore Search und ein SSL-Zertifikat. Danach Laravel + Breeze + Indexer,
#  Datenbank, Cronjob und den ersten Admin.
#
#  Ohne Rückfragen (Beispiel):
#    sudo DOMAIN=indexer.example.com ADMIN_EMAIL=ich@example.com \
#         INSTALL_MANTICORE=ja INSTALL_SSL=ja NONINTERACTIVE=1 bash install.sh
# =============================================================================
set -Eeuo pipefail

# ---------- Einstellungen (per Umgebungsvariable änderbar) ----------
APP_DIR="${APP_DIR:-/var/www/indexer}"
DOMAIN="${DOMAIN:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
INSTALL_MANTICORE="${INSTALL_MANTICORE:-}"     # ja | nein
INSTALL_SSL="${INSTALL_SSL:-}"                 # ja | nein
NONINTERACTIVE="${NONINTERACTIVE:-0}"          # 1 = keine Fragen, Standardwerte nehmen
NODE_MAJOR="${NODE_MAJOR:-24}"
DB_NAME="${DB_NAME:-indexer}"
DB_USER="${DB_USER:-indexer}"
WEB_USER="${WEB_USER:-www-data}"
PHP_VERSION="8.5"
NGINX_SITE="${NGINX_SITE:-/etc/nginx/sites-available/usenet-indexer}"
NGINX_ENABLED_DIR="${NGINX_ENABLED_DIR:-/etc/nginx/sites-enabled}"
CRON_FILE="${CRON_FILE:-/etc/cron.d/usenet-indexer}"
PRE_SERVICE="${PRE_SERVICE:-/etc/systemd/system/drstr4nge-pre.service}"
LOG_FILE="${LOG_FILE:-/var/log/usenet-indexer-install.log}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OVERLAY_DIRS=(app config database resources routes tests)
export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_NO_INTERACTION=1

# ---------- Ausgabe ----------
info()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()    { printf '\033[1;32m    OK: %s\033[0m\n' "$*"; }
warn()  { printf '\033[1;33m    WARNUNG: %s\033[0m\n' "$*" >&2; }
die()   { printf '\n\033[1;31mFEHLER: %s\033[0m\n' "$*" >&2; exit 1; }

on_error() {
    local code=$? line=$1
    printf '\n\033[1;31mFEHLER in Zeile %s (Code %s). Details im Log: %s\033[0m\n' "$line" "$code" "$LOG_FILE" >&2
    exit "$code"
}

# ---------- Hilfsfunktionen ----------
is_yes() { [[ "${1,,}" =~ ^(j|ja|y|yes|1|true)$ ]]; }

# ask VAR "Frage" "Standard"
ask() {
    local var=$1 question=$2 default=${3:-} answer
    if [[ -n "${!var}" ]]; then return; fi
    if [[ "$NONINTERACTIVE" == "1" || ! -t 0 ]]; then
        printf -v "$var" '%s' "$default"
        return
    fi
    read -rp "$question [${default}]: " answer
    printf -v "$var" '%s' "${answer:-$default}"
}

random_secret() { openssl rand -hex 16; }

is_ip() { [[ "$1" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]; }

valid_domain() { [[ "$1" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; }

# Sichere Prüfung des Zielordners: absolut, mindestens 2 Ebenen, nur harmlose Zeichen
valid_app_dir() {
    [[ "$1" =~ ^/[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)+$ ]] && [[ "$1" != *..* ]]
}

# .env-Werte setzen. Modus "if-missing": nur setzen, wenn der Schlüssel noch nicht aktiv gesetzt ist.
ENV_TOOL=""
make_env_tool() {
    ENV_TOOL="$(mktemp --suffix=.php)"
    cat > "$ENV_TOOL" <<'PHP'
<?php
[$self, $file, $key, $value] = $argv + [null, null, null, ''];
$mode = $argv[4] ?? '';
$lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
if (preg_match('/[\s#"\'\\\\]/', $value)) {
    $value = '"' . addcslashes($value, '"\\') . '"';
}
$active = '/^\s*' . preg_quote($key, '/') . '\s*=/';
$commented = '/^\s*#\s*' . preg_quote($key, '/') . '\s*=/';
foreach ($lines as $l) {
    if ($mode === 'if-missing' && preg_match($active, $l)) {
        exit(0);
    }
}
$done = false;
foreach ($lines as $i => $l) {
    if (preg_match($active, $l) || preg_match($commented, $l)) {
        if (!$done) {
            $lines[$i] = "$key=$value";
            $done = true;
        } elseif (preg_match($active, $l)) {
            unset($lines[$i]); // doppelte Einträge entfernen
        }
    }
}
if (!$done) {
    $lines[] = "$key=$value";
}
file_put_contents($file, implode("\n", $lines) . "\n");
PHP
}

set_env() { php "$ENV_TOOL" "$APP_DIR/.env" "$1" "$2" "${3:-}"; }

# composer.json: "php": "^8.5" eintragen (Objekte bleiben Objekte)
require_php_in_composer() {
    php -r '
        $f = $argv[1];
        $j = json_decode(file_get_contents($f));
        if (!$j) { fwrite(STDERR, "composer.json unlesbar\n"); exit(1); }
        $j->require ??= new stdClass();
        $j->require->php = "^8.5";
        file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    ' "$1"
}

# Zeitplan in routes/console.php ergänzen (nur einmal)
add_schedule() {
    local file=$1 manticore=$2
    # Neuere Einträge auch bei bestehendem Zeitplan ergänzen
    if grep -q 'indexer:crawl' "$file"; then
        grep -q 'indexer:pre-fetch' "$file" || echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:pre-fetch')->everyFiveMinutes()->withoutOverlapping();" >> "$file"
        return 0
    fi
    {
        echo ''
        echo '// --- DrStr4nge Indexer ---'
        echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:crawl')->everyTenMinutes()->withoutOverlapping();"
        echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:inspect')->everyFifteenMinutes()->withoutOverlapping();"
        echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:metadata')->everyThirtyMinutes()->withoutOverlapping();"
        echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:cleanup')->dailyAt('04:00');"
        echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:pre-fetch')->everyFiveMinutes()->withoutOverlapping();"
        if is_yes "$manticore"; then
            echo "\\Illuminate\\Support\\Facades\\Schedule::command('indexer:search-index')->weeklyOn(1, '05:00');"
        fi
    } >> "$file"
}

write_nginx_site() {
    local target=$1
    cat > "$target" <<'NGINX'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name __DOMAIN__;
    root __ROOT__/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Cover-Bilder (WebP) lange cachen
    location ~* \.webp$ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php__PHP__-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
    sed -i -e "s|__DOMAIN__|${DOMAIN}|g" -e "s|__ROOT__|${APP_DIR}|g" -e "s|__PHP__|${PHP_VERSION}|g" "$target"
}

write_cron() {
    local target=$1
    printf '# DrStr4nge Indexer: Laravel-Zeitplan jede Minute\nSHELL=/bin/bash\n* * * * * %s cd %s && php artisan schedule:run >> /dev/null 2>&1\n' \
        "$WEB_USER" "$APP_DIR" > "$target"
    chmod 644 "$target"
}

# Dienst für den IRC-Pre-Scraper (läuft dauerhaft, startet bei Absturz neu)
write_pre_service() {
    local target=$1
    cat > "$target" <<UNIT
[Unit]
Description=DrStr4nge Indexer: Pre-Daten aus IRC
After=network-online.target mariadb.service
Wants=network-online.target

[Service]
User=${WEB_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan indexer:pre-irc
Restart=on-failure
RestartSec=60

[Install]
WantedBy=multi-user.target
UNIT
    chmod 644 "$target"
}

# ---------- Schritte ----------
check_system() {
    info "System prüfen"
    [[ $EUID -eq 0 ]] || die "Bitte mit sudo starten: sudo bash install.sh"
    # shellcheck disable=SC1091
    . /etc/os-release
    if [[ "${ID:-}" != "ubuntu" || "${VERSION_ID:-}" != "24.04" ]]; then
        warn "Gedacht für Ubuntu 24.04, gefunden: ${PRETTY_NAME:-unbekannt}."
        [[ "${FORCE:-0}" == "1" ]] || die "Abbruch. Mit FORCE=1 trotzdem versuchen."
    fi
    [[ -f "$SCRIPT_DIR/app/Services/ReleaseBuilder.php" ]] || die "install.sh muss im entpackten Indexer-Ordner liegen."
    valid_app_dir "$APP_DIR" || die "Ungültiger Zielordner: $APP_DIR"
    [[ "$DB_NAME" =~ ^[A-Za-z0-9_]{1,64}$ && "$DB_USER" =~ ^[A-Za-z0-9_]{1,32}$ ]] || die "DB_NAME/DB_USER: nur Buchstaben, Ziffern, _"
    [[ "$WEB_USER" =~ ^[a-z_][a-z0-9_-]*$ ]] && id "$WEB_USER" >/dev/null 2>&1 || die "System-User $WEB_USER fehlt."
    if [[ -e "$APP_DIR" ]] && [[ -n "$(ls -A "$APP_DIR" 2>/dev/null)" ]]; then
        die "$APP_DIR existiert schon und ist nicht leer. Für ein Update siehe README (Abschnitt Update)."
    fi
    ok "Ubuntu ${VERSION_ID:-?}, Zielordner $APP_DIR"
}

ask_settings() {
    info "Einstellungen"
    local ip
    ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    ask DOMAIN "Domain oder IP des Servers" "${ip:-localhost}"
    valid_domain "$DOMAIN" || die "Ungültige Domain: $DOMAIN"
    ask ADMIN_EMAIL "E-Mail für den ersten Admin" "admin@${DOMAIN}"
    [[ "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+$ ]] || die "Ungültige E-Mail: $ADMIN_EMAIL"
    ask INSTALL_MANTICORE "Manticore Search installieren? (ja/nein)" "ja"
    if is_ip "$DOMAIN" || [[ "$DOMAIN" == "localhost" ]]; then
        INSTALL_SSL="nein"
    else
        ask INSTALL_SSL "SSL-Zertifikat von Let's Encrypt holen? Domain muss schon auf den Server zeigen (ja/nein)" "nein"
    fi
    ok "Domain: $DOMAIN | Admin: $ADMIN_EMAIL | Manticore: $INSTALL_MANTICORE | SSL: $INSTALL_SSL"
}

install_base() {
    info "Basis-Pakete"
    apt-get update -y
    apt-get install -y ca-certificates curl gnupg unzip git software-properties-common acl cron openssl
    ok "Basis-Pakete installiert"
}

install_php() {
    info "PHP ${PHP_VERSION} (ondrej/php)"
    LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
    apt-get update -y
    apt-get install -y \
        "php${PHP_VERSION}-cli" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-common" \
        "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-mbstring" \
        "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" \
        "php${PHP_VERSION}-gd" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-intl" \
        "php${PHP_VERSION}-readline"
    update-alternatives --set php "/usr/bin/php${PHP_VERSION}" || true
    systemctl enable --now "php${PHP_VERSION}-fpm"
    php -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);' || die "php zeigt nicht auf PHP 8.5 ($(php -r 'echo PHP_VERSION;'))."
    php -r 'exit(function_exists("imagewebp") ? 0 : 1);' || warn "GD ohne WebP: Cover werden nicht gespeichert."
    ok "$(php -r 'echo "PHP ", PHP_VERSION;')"
}

install_composer() {
    info "Composer"
    if command -v composer >/dev/null 2>&1; then
        ok "schon vorhanden: $(composer --version 2>/dev/null | head -n1)"
        return
    fi
    local tmp expected actual
    tmp="$(mktemp -d)"
    expected="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '$tmp/composer-setup.php');"
    actual="$(php -r "echo hash_file('sha384', '$tmp/composer-setup.php');")"
    [[ -n "$expected" && "$expected" == "$actual" ]] || { rm -rf "$tmp"; die "Composer-Installer: Prüfsumme falsch."; }
    php "$tmp/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp"
    ok "$(composer --version | head -n1)"
}

install_node() {
    info "Node.js ${NODE_MAJOR}"
    if command -v node >/dev/null 2>&1; then
        local have
        have="$(node -p 'process.versions.node.split(".")[0]')"
        if (( have >= 20 )); then
            ok "schon vorhanden: Node $(node -v)"
            return
        fi
    fi
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor --yes -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_MAJOR}.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list
    apt-get update -y
    apt-get install -y nodejs
    ok "Node $(node -v), npm $(npm -v)"
}

DB_PASS=""
install_mariadb() {
    info "MariaDB"
    apt-get install -y mariadb-server
    systemctl enable --now mariadb
    DB_PASS="$(random_secret)"
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    ok "Datenbank ${DB_NAME}, User ${DB_USER} (Passwort steht später in der .env)"
}

install_nginx() {
    info "Nginx"
    apt-get install -y nginx
    write_nginx_site "$NGINX_SITE"
    ln -sf "$NGINX_SITE" "$NGINX_ENABLED_DIR/usenet-indexer"
    rm -f "$NGINX_ENABLED_DIR/default"
    nginx -t
    systemctl enable nginx
    systemctl reload nginx || systemctl restart nginx
    if command -v ufw >/dev/null 2>&1 && ufw status | grep -q 'Status: active'; then
        ufw allow 'Nginx Full' || true
        ok "Firewall: Port 80/443 freigegeben"
    fi
    ok "Nginx eingerichtet"
}

# Pakete holen und starten. Gibt 1 zurück statt abzubrechen (wird in "if" aufgerufen).
manticore_packages() {
    local tmp
    tmp="$(mktemp -d)" || return 1
    curl -fsSLo "$tmp/manticore-repo.noarch.deb" https://repo.manticoresearch.com/manticore-repo.noarch.deb || { rm -rf "$tmp"; return 1; }
    apt-get install -y "$tmp/manticore-repo.noarch.deb" || { rm -rf "$tmp"; return 1; }
    rm -rf "$tmp"
    apt-get update -y || return 1
    # Seit Manticore 25 ist alles (Buddy, Columnar usw.) im Paket "manticore" enthalten.
    # "manticore-extra" zusätzlich zu installieren führt zum Fehler "manticore : Breaks: manticore-extra".
    apt-get install -y manticore || return 1
    systemctl enable --now manticore || return 1
}

install_manticore() {
    is_yes "$INSTALL_MANTICORE" || return 0
    info "Manticore Search"
    if ! manticore_packages; then
        warn "Manticore ließ sich nicht installieren. Die Installation läuft ohne Manticore weiter (Suche per MySQL)."
        warn "Später nachholen: siehe README, Abschnitt Manticore Search."
        INSTALL_MANTICORE="nein"
        return 0
    fi
    local conf=/etc/manticoresearch/manticore.conf
    if [[ -f "$conf" ]] && grep -E '^\s*listen\s*=' "$conf" | grep -vq '127\.0\.0\.1'; then
        warn "Manticore lauscht evtl. nicht nur lokal. Bitte 'listen' in $conf prüfen (empfohlen: 127.0.0.1:9306:mysql)."
    fi
    ok "Manticore läuft"
}

create_app() {
    info "Laravel + Breeze"
    mkdir -p "$(dirname "$APP_DIR")"
    local version ok_breeze=0
    # Erst die neueste Laravel-Version; passt Breeze nicht, automatisch Laravel 12 (unterstützt PHP 8.5 ebenfalls)
    for version in "" "^12.0"; do
        rm -rf "$APP_DIR"
        composer create-project --prefer-dist "laravel/laravel${version:+:$version}" "$APP_DIR"
        if (cd "$APP_DIR" && composer require laravel/breeze --dev); then
            ok_breeze=1
            break
        fi
        warn "Breeze passt nicht zu dieser Laravel-Version, versuche Laravel 12."
    done
    (( ok_breeze == 1 )) || die "Breeze ließ sich nicht installieren."
    (cd "$APP_DIR" && php artisan breeze:install blade --no-interaction)
    ok "$(cd "$APP_DIR" && php artisan --version)"
}

install_indexer() {
    info "Indexer-Dateien kopieren"
    local d
    for d in "${OVERLAY_DIRS[@]}"; do
        mkdir -p "$APP_DIR/$d"
        cp -a "$SCRIPT_DIR/$d/." "$APP_DIR/$d/"
    done
    cp -a "$SCRIPT_DIR/docs/ANLEITUNG.md" "$APP_DIR/README-INDEXER.md" 2>/dev/null || cp -a "$SCRIPT_DIR/README.md" "$APP_DIR/README-INDEXER.md"
    cp -a "$SCRIPT_DIR/VERSION" "$APP_DIR/VERSION" 2>/dev/null || true
    cp -a "$SCRIPT_DIR/docker-compose.manticore.yml" "$APP_DIR/" 2>/dev/null || true
    require_php_in_composer "$APP_DIR/composer.json"
    (cd "$APP_DIR" && composer update --lock --no-interaction) || warn "composer.lock-Hash nicht aktualisiert (harmlos)."
    add_schedule "$APP_DIR/routes/console.php" "$INSTALL_MANTICORE"
    ok "Dateien kopiert, PHP ^8.5 in composer.json, Zeitplan eingetragen"
}

configure_env() {
    info ".env einrichten"
    local scheme="http"
    is_yes "$INSTALL_SSL" && scheme="https"
    set_env APP_NAME "DrStr4nge Indexer"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "${scheme}://${DOMAIN}"
    set_env DB_CONNECTION mariadb
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASS"
    # Indexer-Einstellungen aus env.additions.txt (nur wenn noch nicht gesetzt)
    local line key value
    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ "$line" =~ ^[[:space:]]*# || ! "$line" =~ = ]] && continue
        key="${line%%=*}"
        value="${line#*=}"
        [[ "$key" =~ ^[A-Z0-9_]+$ ]] || continue
        set_env "$key" "$value" if-missing
    done < "$SCRIPT_DIR/env.additions.txt"
    if is_yes "$INSTALL_MANTICORE"; then
        set_env MANTICORE_ENABLED true
    fi
    chmod 640 "$APP_DIR/.env"
    ok ".env fertig (Datenbank, Indexer-Werte)"
}

finish_app() {
    info "Datenbank, Frontend, Rechte"
    cd "$APP_DIR"
    php artisan config:clear
    php artisan migrate --force
    php artisan storage:link || true
    npm install
    npm run build
    if is_yes "$INSTALL_MANTICORE"; then
        # --fresh: Reste einer früheren Installation aus dem Suchindex entfernen
        php artisan indexer:search-index --fresh || warn "Suchindex nicht angelegt. Später: php artisan indexer:search-index --fresh"
    fi
    echo
    php artisan indexer:make-admin "$ADMIN_EMAIL" --create
    echo
    chown -R "$WEB_USER:$WEB_USER" "$APP_DIR"
    chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    write_cron "$CRON_FILE"
    write_pre_service "$PRE_SERVICE"
    systemctl daemon-reload && systemctl enable --now drstr4nge-pre || warn "Pre-Dienst nicht gestartet. Später: systemctl enable --now drstr4nge-pre"
    systemctl reload "php${PHP_VERSION}-fpm" || true
    ok "Fertig eingerichtet"
}

install_ssl() {
    is_yes "$INSTALL_SSL" || return 0
    info "SSL-Zertifikat (Let's Encrypt)"
    apt-get install -y certbot python3-certbot-nginx
    if certbot --nginx -d "$DOMAIN" -m "$ADMIN_EMAIL" --agree-tos --non-interactive --redirect; then
        ok "HTTPS aktiv"
    else
        warn "Zertifikat fehlgeschlagen. Zeigt die Domain auf diesen Server? Später: certbot --nginx -d $DOMAIN"
        set_env APP_URL "http://${DOMAIN}"
    fi
}

summary() {
    local scheme="http"
    is_yes "$INSTALL_SSL" && grep -q '^APP_URL=https' "$APP_DIR/.env" && scheme="https"
    cat <<EOF

=============================================================================
 Installation abgeschlossen
=============================================================================
 Adresse:      ${scheme}://${DOMAIN}
 Admin:        ${ADMIN_EMAIL}  (Passwort steht oben, bitte notieren; auch im Log, nur für root lesbar)
 Ordner:       ${APP_DIR}
 Einstellungen: ${APP_DIR}/.env
 Anleitung:    ${APP_DIR}/README-INDEXER.md
 Log:          ${LOG_FILE}

 Jetzt noch erledigen:
 1. In der .env deinen Usenet-Zugang eintragen: NNTP_HOST, NNTP_USER, NNTP_PASS
    Optional: TMDB_API_KEY (Cover), BTCPAY_* (Krypto-Zahlung)
    Danach: cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan config:clear
 2. Verbindung prüfen: cd ${APP_DIR} && php artisan indexer:test-nntp
    Einloggen, im Admin-Bereich unter "Newsgroups" Gruppen eintragen.
    Name der Seite und Logo: Admin > Einstellungen.
 3. Ersten Crawl starten:
    cd ${APP_DIR} && sudo -u ${WEB_USER} php artisan indexer:crawl
    Danach läuft alles automatisch über den Cronjob.
=============================================================================
EOF
}

main() {
    mkdir -p "$(dirname "$LOG_FILE")"
    touch "$LOG_FILE" && chmod 600 "$LOG_FILE"   # enthält das Admin-Passwort: nur root darf lesen
    exec > >(tee -a "$LOG_FILE") 2>&1
    trap 'on_error $LINENO' ERR
    echo "DrStr4nge Indexer $(cat "$SCRIPT_DIR/VERSION" 2>/dev/null) - Installer - $(date)"

    check_system
    ask_settings
    make_env_tool
    trap 'rm -f "$ENV_TOOL"' EXIT

    install_base
    install_php
    install_composer
    install_node
    install_mariadb
    install_manticore
    create_app
    install_indexer
    configure_env
    install_nginx
    finish_app
    install_ssl
    summary
}

# Nur ausführen, wenn direkt gestartet (nicht beim Einbinden für Tests)
if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    main "$@"
fi
