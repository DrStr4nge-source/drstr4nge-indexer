<?php
return [
    'version' => '1.13.4',
    // Zeitzone für Anzeige und Zeitplan (gespeichert wird in UTC)
    'timezone' => env('INDEXER_TIMEZONE', 'Europe/Berlin'),

    'nntp' => [
        'host' => env('NNTP_HOST'),
        'port' => (int) env('NNTP_PORT', 563),
        'ssl'  => filter_var(env('NNTP_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'user' => env('NNTP_USER'),
        'pass' => env('NNTP_PASS'),
        'connections' => (int) env('NNTP_CONNECTIONS', 4), // gleichzeitige Verbindungen des Crawlers (eine Gruppe je Verbindung)
    ],
    'backfill'           => (int) env('INDEXER_BACKFILL', 100000), // Artikel beim ersten Crawl (wenn backfill_days = 0)
    'backfill_days'      => (int) env('INDEXER_BACKFILL_DAYS', 0),    // erster Crawl: so viele Tage zurück, 0 = INDEXER_BACKFILL nutzen

    // Pre-Daten aus IRC (indexer:pre-irc, Dienst drstr4nge-pre). Gleiche Namen wie bei NNTmux.
    'pre_irc' => [
        'server' => env('SCRAPE_IRC_SERVER', 'irc.synirc.net'),
        'port' => (int) env('SCRAPE_IRC_PORT', 6697),
        'tls' => (bool) env('SCRAPE_IRC_TLS', true),
        'tls_verify' => (bool) env('SCRAPE_IRC_TLS_VERIFY', true),
        'nick' => env('SCRAPE_IRC_USERNAME', ''),
        'username' => env('SCRAPE_IRC_USERNAME', ''),
        'realname' => env('SCRAPE_IRC_REALNAME', env('SCRAPE_IRC_USERNAME', '')),
        'password' => env('SCRAPE_IRC_PASSWORD'),
        // ohne Anführungszeichen wäre "#..." in der .env ein Kommentar: dann Standard; "#" wird ergänzt, falls es fehlt
        'channels' => array_values(array_map(fn ($c) => $c[0] === '#' ? $c : '#' . $c,
            array_filter(array_map('trim', explode(',', (string) (env('SCRAPE_IRC_CHANNELS') ?: '#PreNNTmux,#nZEDbPRE')))))),
        'category_ignore' => env('SCRAPE_IRC_CATEGORY_IGNORE', ''),
        'title_ignore' => env('SCRAPE_IRC_TITLE_IGNORE', ''),
    ],

    // TVmaze: TVDB-/IMDb-/TVmaze-IDs für Serien ergänzen (indexer:tvmaze, stündlich)
    'tvmaze' => (bool) env('TVMAZE_ENABLED', true),

    // Verschleierte Releases über Größe/Dateianzahl/Zeit mit der PreDB benennen (indexer:pre-deobfuscate)
    'pre_deobfuscate' => (bool) env('PRE_DEOBFUSCATE', true),

    // Pre-Daten von predb.net (indexer:pre-fetch, alle 5 Minuten)
    'predb_api' => [
        'enabled' => (bool) env('PREDB_API_ENABLED', true),
        'url' => env('PREDB_API_URL', 'https://api.predb.net'),
        'pages' => (int) env('PREDB_API_PAGES', 5),      // höchstens so viele Seiten à 100 pro Lauf
        'lookup' => (int) env('PREDB_API_LOOKUP', 20),   // Releases ohne Pre pro Lauf einzeln nachschlagen
        'pause_ms' => (int) env('PREDB_API_PAUSE_MS', 1500),
    ],

    // Pre-Daten aus RSS-Feeds (mit indexer:pre-fetch, alle 5 Minuten), mehrere mit Komma trennen, leer = aus
    'pre_rss' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRE_RSS_FEEDS', 'https://predb.me/?rss=1'))))),

    // Warn-Mails (indexer:health, alle 15 Minuten)
    'alerts' => [
        'email' => env('ALERT_EMAIL'),                          // leer = alle Admins
        'crawl_hours' => (int) env('ALERT_CRAWL_HOURS', 6),     // Crawler findet so lange nichts: Warnung
        'irc_minutes' => (int) env('ALERT_IRC_MINUTES', 30),
        'disk_percent' => (int) env('ALERT_DISK_PERCENT', 90),
        'repeat_hours' => (int) env('ALERT_REPEAT_HOURS', 12),  // gleiche Warnung höchstens so oft
    ],

    // Tägliche Sicherung (indexer:backup)
    'backup' => [
        'enabled' => (bool) env('BACKUP_ENABLED', true),
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 7),
        'time' => env('BACKUP_TIME', '03:30'),
        'remote' => env('BACKUP_REMOTE'),               // rsync-Ziel, z.B. backup@server2:/srv/backups/
        'rclone_remote' => env('BACKUP_RCLONE_REMOTE'), // z.B. gdrive:drstr4nge-backups (vorher "rclone config")
        'rclone_config' => env('BACKUP_RCLONE_CONFIG'), // optional, Pfad zur rclone.conf
    ],
    'min_completion'     => (int) env('INDEXER_MIN_COMPLETION', 90), // % Teile, ab denen ein Release sichtbar ist
    'free_grabs_per_day' => (int) env('INDEXER_FREE_GRABS', 5),     // VIP = unbegrenzt
    'retention_days'     => (int) env('INDEXER_RETENTION_DAYS', 1000), // ältere Releases werden gelöscht
    'delete_obfuscated_days' => (int) env('INDEXER_DELETE_OBFUSCATED_DAYS', 3), // verschleierte ohne Namen nach X Tagen löschen, 0 = nie
    'delete_passworded_days' => (int) env('INDEXER_DELETE_PASSWORDED_DAYS', 1), // passwortgeschützte nach X Tagen löschen, 0 = nie
    'min_size'           => (int) env('INDEXER_MIN_SIZE', 1048576),    // kleinere Releases gelten als Spam
    'inspect_per_run'    => (int) env('INDEXER_INSPECT_PER_RUN', 100), // Par2/RAR-Prüfungen pro Lauf
    'api_limit'          => (int) env('INDEXER_API_LIMIT', 60),        // API-Aufrufe pro Minute (Standard)
    'api_limit_vip'      => (int) env('INDEXER_API_LIMIT_VIP', 300),   // API-Aufrufe pro Minute (VIP)
    'spam_words'         => array_filter(array_map('trim', explode(',', (string) env('INDEXER_SPAM_WORDS', 'viagra,cialis')))),
    'tmdb' => [
        'key'      => env('TMDB_API_KEY'),            // v3-API-Key oder v4-Lesetoken (beginnt mit eyJ)
        'language' => env('TMDB_LANGUAGE', 'de-DE'),
        'min_match' => (int) env('TMDB_MIN_MATCH', 75), // Titel-Ähnlichkeit in %, darunter wird nicht verknüpft
    ],
    'metadata' => [
        'provider' => env('METADATA_PROVIDER', 'tmdb'), // tmdb | none | eigener Name aus 'providers'
        'providers' => [
            'tmdb' => \App\Services\Tmdb::class,
            // 'meinquelle' => \App\Services\MeineQuelle::class,
        ],
    ],
    'cover' => [
        'width' => (int) env('COVER_WIDTH', 342),
        'quality' => (int) env('COVER_QUALITY', 80),
    ],
    'manticore' => [
        'enabled'     => filter_var(env('MANTICORE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'host'        => env('MANTICORE_HOST', '127.0.0.1'),
        'port'        => (int) env('MANTICORE_PORT', 9306),
        'table'       => env('MANTICORE_TABLE', 'releases_idx'),
        'max_results' => (int) env('MANTICORE_MAX_RESULTS', 10000), // so tief kann man maximal blättern
    ],
    'currency'           => 'EUR',
    'plans' => [
        '1m'  => ['label' => '1 Monat',   'months' => 1,  'price' => 5],
        '3m'  => ['label' => '3 Monate',  'months' => 3,  'price' => 12],
        '12m' => ['label' => '12 Monate', 'months' => 12, 'price' => 40],
    ],
    'payment_provider' => env('PAYMENT_PROVIDER', 'btcpay'), // btcpay | nowpayments
    'nowpayments' => [
        'api_key'       => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret'    => env('NOWPAYMENTS_IPN_SECRET'),
        'pay_currency'  => env('NOWPAYMENTS_PAY_CURRENCY', ''), // z.B. xmr = nur Monero; leer = Kunde wählt
        'sandbox'       => filter_var(env('NOWPAYMENTS_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
        'paid_statuses' => ['finished'], // erst wenn das Geld bei dir angekommen ist
    ],
    'btcpay' => [
        'url'            => env('BTCPAY_URL'),
        'store'          => env('BTCPAY_STORE_ID'),
        'key'            => env('BTCPAY_API_KEY'),
        'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
    ],
    // Newznab-Kategorien: id => [Name, Eltern-ID]
    'categories' => [
        1000 => ['Console', null], 1030 => ['Console/Wii', 1000], 1040 => ['Console/Xbox', 1000],
        1050 => ['Console/Xbox 360', 1000], 1080 => ['Console/PS3', 1000], 1180 => ['Console/PS4', 1000],
        2000 => ['Movies', null], 2030 => ['Movies/SD', 2000], 2040 => ['Movies/HD', 2000],
        2045 => ['Movies/UHD', 2000], 2050 => ['Movies/BluRay', 2000],
        3000 => ['Audio', null], 3010 => ['Audio/MP3', 3000], 3030 => ['Audio/Audiobook', 3000], 3040 => ['Audio/Lossless', 3000],
        4000 => ['PC', null], 4010 => ['PC/0day', 4000], 4020 => ['PC/ISO', 4000], 4030 => ['PC/Mac', 4000], 4050 => ['PC/Games', 4000],
        5000 => ['TV', null], 5030 => ['TV/SD', 5000], 5040 => ['TV/HD', 5000], 5045 => ['TV/UHD', 5000],
        5060 => ['TV/Sport', 5000], 5070 => ['TV/Anime', 5000],
        6000 => ['XXX', null],
        7000 => ['Other', null], 7020 => ['Other/Ebook', 7000], 7030 => ['Other/Comics', 7000],
    ],
];
