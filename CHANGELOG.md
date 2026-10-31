# Changelog

Alle wichtigen Änderungen am DrStr4nge Indexer. Die Versionen bis 1.8 wurden rückblickend aus der Entwicklung zusammengefasst.

## [1.13.0] – 2026-10-31

### Neu
- `indexer:pre-import`: PreDB-Dumps einspielen, mit `--nzedb` alle Tages-Dumps von github.com/nZEDb/nZEDbPre_Dumps (2014 bis Juni 2024), `--since` für ein Startdatum; auch eigene Dateien (nZEDb-Format oder CSV), Ordner und URLs. Vorhandene Einträge bleiben, danach werden Releases verknüpft.

## [1.12.2] – 2026-10-31

### Geändert
- Alte Sicherungen werden in Google Drive endgültig gelöscht statt in den Papierkorb verschoben (der belegt sonst noch 30 Tage Speicherplatz)

## [1.12.1] – 2026-10-31

### Behoben
- Sicherung brach mit "set: Illegal option -o pipefail" ab (Ubuntu nutzt für sh dash statt bash)

## [1.12.0] – 2026-10-31

### Neu
- **Zugangsdaten im Admin:** Usenet, TMDB, Mail, Warn-Empfänger, NOWPayments und Sicherung ohne `.env` eintragen, mit Verbindungs- und Mail-Test
- **Tägliche Sicherung** (Datenbank, `.env`, Logo), 7 Tage aufbewahrt, auf Wunsch zusätzlich nach **Google Drive** (rclone, im Admin verbinden) oder auf einen zweiten Server
- **Zwei-Faktor-Anmeldung** für Admins mit QR-Code und Wiederherstellungscodes, optional Pflicht
- **Überwachung mit Warn-Mails:** Crawler steht, IRC getrennt, Festplatte voll, Sicherung fehlt; Mail auch, wenn es wieder läuft
- **Log im Admin:** Warnungen und Fehler mit Details, Suche und Filter
- `indexer:status`: was gerade läuft, Stand je Gruppe, letzte Läufe; Sperre gegen doppelten Crawl (`--unlock` nach Absturz)
- **Verschleierte Releases über die PreDB benennen** (echte Größe/Dateien aus PAR2, nur eindeutige Treffer)
- **TVmaze:** TVDB-/TVmaze-IDs für Serien, `tvmazeid` in der Newznab-API
- **Merkliste** und **RSS-Feeds** pro User (neu, Merkliste, Kategorien)
- **Releases melden**, Admin-Seite Meldungen
- Betrieb-Kacheln in der Admin-Übersicht
- `update.sh`: Update mit einem Befehl von GitHub; `install.sh` fragt Usenet, TMDB, IRC-Nick, Mail und Google Drive gleich mit ab
- Zeitplan in eigener Datei (`routes/indexer-schedule.php`), kommt mit jedem Update mit

### Geändert
- Neue oder gelöschte Blacklist-Regeln gelten nach spätestens 10 Minuten auch für vorhandene Releases
- Anzeige und Zeitplan in deutscher Zeit (`INDEXER_TIMEZONE`), gespeichert wird weiter in UTC
- "Passwort vergessen", "Neues Passwort", E-Mail bestätigen und "Profil & Passwort" im dunklen Design

## [1.11.1] – 2026-10-31

### Behoben
- Häkchen in Kästchen (Einstellungen, Mehrfachauswahl, Login) waren im dunklen Design unsichtbar

## [1.11.0] – 2026-10-31

### Neu
- Pre-Daten: IRC-Scraper für `#PreNNTmux` und `#nZEDbPRE` (synIRC) als Dienst `drstr4nge-pre`, verbindet sich selbst neu
- Pre-Daten von predb.net, alle 5 Minuten, dazu Nachschlagen von Releases ohne Pre
- Pre-Daten aus RSS-Feeds (`PRE_RSS_FEEDS`, Standard predb.me)
- IRC-Einstellungen im Admin (Server, Port, TLS, Nick, Kanäle, an/aus), der Dienst verbindet ohne Neustart neu
- Releases zeigen "Pre vor X" und Nukes, Admin-Seite **PreDB** mit Suche und Scraper-Status
- Befehle `indexer:pre-irc`, `indexer:pre-fetch`, `indexer:pre-match`

## [1.10.1] – 2026-10-31

### Behoben
- Trennt der Usenet-Server die Verbindung mitten im Abruf, verbindet der Crawler neu und holt den Block noch einmal (bis zu 3 Versuche). Vorher brach der ganze Crawl mit "Connection reset by peer" ab.
- Ein abgebrochener Header-Abruf wird nicht mehr halb gespeichert. Vorher konnten dabei Artikel übersprungen werden.

## [1.10.0] – 2026-10-31

### Neu
- Backfill nach Tagen: `INDEXER_BACKFILL_DAYS` (z.B. 60). Der Crawler sucht für jede Gruppe selbst den Artikel, ab dem gelesen wird.
- Admin-Übersicht zeigt die Server-Auslastung: CPU, RAM, Swap, Speicherplatz und Laufzeit, aktualisiert alle 5 Sekunden.

## [1.9.0] – 2026-10-31

### Neu
- Neuer Name: **DrStr4nge Indexer**, Version in Fußzeile und Admin-Übersicht
- Registrierung mit drei Modi (offen, nur mit Einladungscode, geschlossen), Einladungscodes im Admin, Rate-Limit und Bot-Falle
- Login und Registrierung im dunklen Design, Link zur Registrierung auf der Login-Seite
- Zahlung über **NOWPayments** (z.B. Monero, ohne eigenen Node), wählbar per `PAYMENT_PROVIDER`; Rückmeldungen per Signatur geprüft
- Lizenz: GNU GPL-3.0
- Anleitung neu gegliedert (`docs/ANLEITUNG.md`) mit Abschnitt zur Fehlersuche, Screenshots, Changelog

### Geändert
- Blacklist-Namensregeln greifen erst, wenn der echte Name eines Releases bekannt ist
- VIP-Gutschrift gemeinsam für BTCPay und NOWPayments, doppelte Rückmeldungen verlängern nur einmal
- Rückmelde-Adresse für Zahlungen kommt fest aus `APP_URL`

## [1.8.0]

### Neu
- Seiten **Filme** und **Serien** mit Karten: Cover, Jahr, Bewertung, IMDb/TMDB-Links, Inhalt, Genre, Regie, Darsteller und Releases
- TMDB liefert zusätzlich Genre, Regie (bei Serien: Erfinder) und Darsteller; bekannte Titel werden automatisch nachgeladen
- Admin: Releases mit Mehrfachauswahl, Suche, "auf alle Treffer anwenden" und "Poster sperren"
- Automatisches Löschen verschleierter Releases ohne Namen und passwortgeschützter Releases (einstellbar)

## [1.7.0]

### Neu
- Verbindungstest `indexer:test-nntp` mit verständlichen Fehlermeldungen
- Mehrere NNTP-Verbindungen gleichzeitig (`NNTP_CONNECTIONS`), eine Newsgroup je Verbindung
- Neuer Betreff-Parser: versteht auch Größenangaben und Platzhalter im Betreff, Latin-1 wird nach UTF-8 umgewandelt
- Admin: Newsgroup "Neu einlesen"

### Behoben
- Fehler 500 beim Vergeben von VIP (Carbon 3 verlangt Zahlen)
- Fehler 500 beim VIP-Kauf ohne eingerichtete Zahlung
- Logo und Cover nicht sichtbar bei abweichender Adresse; Warnung bei fehlendem `storage:link`
- Eine fehlerhafte Newsgroup stoppt den Crawler nicht mehr
- Lange Wartezeit, wenn der Usenet-Server nicht antwortet

## [1.6.0]

### Neu
- Dunkles Design mit orangem Akzent, Navigation mit Suche, Handy-Ansicht
- Startseite mit Cover-Reihen, Detailseite pro Film/Serie, Konto-Seite
- Admin-Einstellungen: Name der Seite und Logo-Upload

## [1.5.0]

### Neu
- `install.sh`: Installation auf Ubuntu 24.04 mit einem Befehl (PHP 8.5, Composer, Node.js, MariaDB, Nginx, optional Manticore und SSL)

### Behoben
- Manticore-Installation (nur Paket `manticore`, nicht zusätzlich `manticore-extra`)

## [1.4.0]

### Neu
- Optionale Volltextsuche mit **Manticore Search**, automatischer Rückfall auf MySQL
- Ausgelegt auf **PHP 8.5**

## [1.3.0]

### Neu
- Austauschbare Metadaten-Quelle (`METADATA_PROVIDER`)

## [1.2.0]

### Neu
- Metadaten von TMDB inkl. IMDb- und TVDB-ID, Cover als WebP
- Newznab-Suche über `imdbid`, `tmdbid`, `tvdbid`

## [1.1.0]

### Neu
- Kategorien nach Newznab, Erkennung verschleierter Namen über PAR2, Passwort-Erkennung für RAR
- Spam-Erkennung und Blacklist, automatisches Aufräumen, Rate-Limit für die API, Tests

## [1.0.0]

### Neu
- Crawler, Releases, NZB-Export, Newznab-API
- User- und Admin-Bereich, VIP-Zugang per Krypto-Zahlung über BTCPay Server
