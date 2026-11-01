#!/usr/bin/env bash
# DrStr4nge Indexer: Sicherung zusätzlich nach Google Drive kopieren (über rclone).
#   sudo bash setup-gdrive.sh
# Braucht einmal einen PC mit Browser für die Google-Anmeldung.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/install.sh"

GDRIVE_REMOTE="${GDRIVE_REMOTE:-gdrive}"
GDRIVE_FOLDER="${GDRIVE_FOLDER:-drstr4nge-backups}"

gdrive_main() {
    [[ $EUID -eq 0 ]] || die "Bitte mit sudo starten: sudo bash setup-gdrive.sh"
    [[ -f "$APP_DIR/.env" ]] || die "Keine Installation in $APP_DIR gefunden."
    command -v rclone >/dev/null || { info "rclone installieren"; command -v install_rclone >/dev/null && install_rclone || apt-get install -y rclone >/dev/null; }

    local conf_dir="$APP_DIR/storage/rclone" conf
    conf="$conf_dir/rclone.conf"
    mkdir -p "$conf_dir"
    chown "$WEB_USER:$WEB_USER" "$conf_dir"
    chmod 700 "$conf_dir"
    rc() { sudo -u "$WEB_USER" rclone --config "$conf" "$@"; }

    info "Google Drive verbinden"
    cat <<TXT
    So geht die Anmeldung (einmalig):
    1. Auf deinem PC rclone herunterladen: https://rclone.org/downloads/
       (Windows: ZIP entpacken, im Ordner Eingabeaufforderung öffnen)
    2. Gleich zeigt dieses Skript einen Befehl wie:  rclone authorize "drive" "eyJ..."
       Diesen Befehl auf dem PC ausführen. Der Browser öffnet sich, bei Google anmelden, erlauben.
    3. Der PC zeigt danach einen langen Text (Token). Den komplett hier einfügen und Enter drücken.

    rclone bekommt nur Zugriff auf Dateien, die es selbst anlegt (nicht auf deinen ganzen Drive).
TXT
    read -rp "    Weiter mit Enter (Abbruch mit Strg+C) " _

    if rc listremotes 2>/dev/null | grep -qx "${GDRIVE_REMOTE}:"; then
        ok "Verbindung \"$GDRIVE_REMOTE\" gibt es schon, wird neu angelegt"
        rc config delete "$GDRIVE_REMOTE"
    fi
    # config_is_local=false: Anmeldung auf einem anderen Gerät (Server hat keinen Browser)
    rc config create "$GDRIVE_REMOTE" drive scope=drive.file config_is_local=false \
        || die "Verbindung nicht angelegt. Von Hand: sudo -u $WEB_USER rclone --config $conf config"
    chmod 600 "$conf"

    info "Verbindung testen"
    rc mkdir "${GDRIVE_REMOTE}:${GDRIVE_FOLDER}" || die "Ordner in Google Drive nicht angelegt. Token prüfen und Skript erneut starten."
    echo "test $(date)" | rc rcat "${GDRIVE_REMOTE}:${GDRIVE_FOLDER}/.verbindungstest" && rc deletefile "${GDRIVE_REMOTE}:${GDRIVE_FOLDER}/.verbindungstest"
    ok "Google Drive erreichbar, Ordner: $GDRIVE_FOLDER"

    make_env_tool
    trap 'rm -f "$ENV_TOOL"' EXIT
    set_env BACKUP_RCLONE_REMOTE "${GDRIVE_REMOTE}:${GDRIVE_FOLDER}"
    set_env BACKUP_RCLONE_CONFIG "$conf"
    (cd "$APP_DIR" && sudo -u "$WEB_USER" php artisan config:clear >/dev/null)
    ok ".env: BACKUP_RCLONE_REMOTE=${GDRIVE_REMOTE}:${GDRIVE_FOLDER}"

    local now="${GDRIVE_TEST_BACKUP:-}"
    ask now "Jetzt eine Sicherung machen und hochladen? (ja/nein)" "ja"
    if is_yes "$now"; then
        (cd "$APP_DIR" && sudo -u "$WEB_USER" php artisan indexer:backup) || warn "Sicherung fehlgeschlagen, siehe Meldung oben"
    fi
    ok "Fertig. Ab jetzt landet jede nächtliche Sicherung auch in Google Drive."
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    gdrive_main "$@"
fi
