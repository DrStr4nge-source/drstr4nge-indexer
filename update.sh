#!/usr/bin/env bash
# DrStr4nge Indexer: Update einer bestehenden Installation.
#   cd /tmp && rm -rf dsi && git clone -q https://github.com/DrStr4nge-source/drstr4nge-indexer.git dsi && sudo bash dsi/update.sh
# Daten, .env, Logo und Cover bleiben erhalten. config/indexer.php wird überschrieben.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Funktionen und Standardwerte aus install.sh (ohne die Installation zu starten)
# shellcheck disable=SC1091
source "$SCRIPT_DIR/install.sh"

update_main() {
    [[ $EUID -eq 0 ]] || die "Bitte mit sudo starten: sudo bash update.sh"
    [[ -f "$APP_DIR/artisan" && -f "$APP_DIR/.env" ]] || die "Keine Installation in $APP_DIR gefunden. Anderer Ordner: APP_DIR=/pfad sudo -E bash update.sh"
    local old new
    old="$(grep -oP "'version'\s*=>\s*'\K[^']+" "$APP_DIR/config/indexer.php" 2>/dev/null || echo '?')"
    new="$(cat "$SCRIPT_DIR/VERSION" 2>/dev/null || echo '?')"
    info "Update DrStr4nge Indexer $old -> $new ($APP_DIR)"

    info "Dateien kopieren"
    local d
    for d in "${OVERLAY_DIRS[@]}"; do
        mkdir -p "$APP_DIR/$d"
        cp -a "$SCRIPT_DIR/$d/." "$APP_DIR/$d/"
    done
    cp -a "$SCRIPT_DIR/VERSION" "$APP_DIR/VERSION"
    cp -a "$SCRIPT_DIR/docs/ANLEITUNG.md" "$APP_DIR/README-INDEXER.md" 2>/dev/null || true
    add_schedule "$APP_DIR/routes/console.php"
    add_provider "$APP_DIR/bootstrap/providers.php"
    ok "Dateien kopiert, Zeitplan aktualisiert"

    info "Neue Einstellungen in die .env übernehmen"
    make_env_tool
    trap 'rm -f "$ENV_TOOL"' EXIT
    merge_env_additions
    ok ".env ergänzt (vorhandene Werte unverändert)"

    info "Datenbank und Zwischenspeicher"
    if command -v mysql >/dev/null; then tune_mariadb && ok "MariaDB: max_allowed_packet 64 MB"; fi
    chown -R "$WEB_USER:$WEB_USER" "$APP_DIR"
    (cd "$APP_DIR" && sudo -u "$WEB_USER" php artisan migrate --force \
        && sudo -u "$WEB_USER" php artisan config:clear && sudo -u "$WEB_USER" php artisan route:clear \
        && sudo -u "$WEB_USER" php artisan view:clear && sudo -u "$WEB_USER" php artisan event:clear)
    # Kein optimize:clear/cache:clear: im Cache stehen Status, Sicherungszeit, Überwachung und Crawl-Sperren
    # Konfiguration, Routen, Ansichten und Events vorab zusammenstellen: jede Seite lädt schneller
    (cd "$APP_DIR" && sudo -u "$WEB_USER" php artisan config:cache && sudo -u "$WEB_USER" php artisan route:cache \
        && sudo -u "$WEB_USER" php artisan view:cache && sudo -u "$WEB_USER" php artisan event:cache) >/dev/null \
        || { warn "Zwischenspeichern nicht möglich, läuft trotzdem (nur etwas langsamer)"; (cd "$APP_DIR" && sudo -u "$WEB_USER" php artisan config:clear && sudo -u "$WEB_USER" php artisan route:clear) >/dev/null; }
    ok "Datenbank aktuell"

    # Werkzeuge für Sicherung (rsync, rclone für Google Drive)
    if ! command -v rclone >/dev/null || ! command -v rsync >/dev/null; then
        apt-get install -y rsync rclone >/dev/null && ok "rsync und rclone installiert" || warn "rsync/rclone nicht installiert (nur für Kopie der Sicherung nötig)"
    fi

    info "Pre-Dienst"
    if [[ ! -f "$PRE_SERVICE" ]]; then
        write_pre_service "$PRE_SERVICE"
        systemctl daemon-reload
        systemctl enable --now drstr4nge-pre || warn "Pre-Dienst nicht gestartet: systemctl status drstr4nge-pre"
        ok "Pre-Dienst eingerichtet"
    else
        systemctl restart drstr4nge-pre || warn "Pre-Dienst nicht neu gestartet: systemctl status drstr4nge-pre"
        ok "Pre-Dienst neu gestartet"
    fi
    systemctl reload "php${PHP_VERSION}-fpm" 2>/dev/null || true

    echo
    ok "Fertig: Version $new. Im Browser mit Strg+F5 neu laden."
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    update_main "$@"
fi
