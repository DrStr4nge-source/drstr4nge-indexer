# Changelog

Alle wichtigen Änderungen am DrStr4nge Indexer. Die Versionen bis 1.8 wurden rückblickend aus der Entwicklung zusammengefasst.

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
