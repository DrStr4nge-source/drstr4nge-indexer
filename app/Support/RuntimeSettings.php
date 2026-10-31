<?php
namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Zugangsdaten aus dem Admin (Tabelle settings) über die Werte aus der .env legen.
 * Leeres Feld im Admin = Wert aus der .env. Passwörter liegen verschlüsselt (APP_KEY) in der Datenbank.
 */
class RuntimeSettings
{
    /** Einstellung => [Konfig-Pfad, Typ, Gruppe, Beschriftung] */
    public const FIELDS = [
        'nntp_host' => ['indexer.nntp.host', 'host', 'usenet', 'Server'],
        'nntp_port' => ['indexer.nntp.port', 'port', 'usenet', 'Port'],
        'nntp_ssl' => ['indexer.nntp.ssl', 'bool', 'usenet', 'SSL'],
        'nntp_user' => ['indexer.nntp.user', 'string', 'usenet', 'Benutzer'],
        'nntp_pass' => ['indexer.nntp.pass', 'secret', 'usenet', 'Passwort'],
        'nntp_connections' => ['indexer.nntp.connections', 'int:1:50', 'usenet', 'Verbindungen'],
        'backfill_days' => ['indexer.backfill_days', 'int:0:5000', 'usenet', 'Erster Crawl: Tage zurück'],
        'tmdb_key' => ['indexer.tmdb.key', 'secret', 'tmdb', 'API-Key'],
        'tmdb_language' => ['indexer.tmdb.language', 'string', 'tmdb', 'Sprache'],
        'mail_host' => ['mail.mailers.smtp.host', 'host', 'mail', 'SMTP-Server'],
        'mail_port' => ['mail.mailers.smtp.port', 'port', 'mail', 'Port'],
        'mail_username' => ['mail.mailers.smtp.username', 'string', 'mail', 'Benutzer'],
        'mail_password' => ['mail.mailers.smtp.password', 'secret', 'mail', 'Passwort'],
        'mail_from_address' => ['mail.from.address', 'email', 'mail', 'Absender'],
        'alert_email' => ['indexer.alerts.email', 'emails', 'mail', 'Warnungen an'],
        'payment_provider' => ['indexer.payment_provider', 'in:btcpay,nowpayments', 'payment', 'Zahlungsart'],
        'nowpayments_api_key' => ['indexer.nowpayments.api_key', 'secret', 'payment', 'NOWPayments API-Key'],
        'nowpayments_ipn_secret' => ['indexer.nowpayments.ipn_secret', 'secret', 'payment', 'NOWPayments IPN-Secret'],
        'nowpayments_pay_currency' => ['indexer.nowpayments.pay_currency', 'string', 'payment', 'Währung (z.B. xmr)'],
        'backup_enabled' => ['indexer.backup.enabled', 'bool', 'backup', 'Tägliche Sicherung'],
        'backup_keep_days' => ['indexer.backup.keep_days', 'int:1:365', 'backup', 'Aufbewahren (Tage)'],
        'backup_rclone_remote' => ['indexer.backup.rclone_remote', 'string', 'backup', 'Cloud-Ziel'],
    ];

    /** Werte aus .env/config vor dem Überschreiben (für die Anzeige "aus .env") */
    public static array $env = [];

    /** Beim Start jeder Anfrage und jedes Befehls (IndexerServiceProvider). */
    public static function apply(): void
    {
        foreach (self::FIELDS as [$path]) {
            self::$env[$path] ??= config($path);
        }
        $set = [];
        foreach (self::FIELDS as $key => [$path, $type]) {
            $v = Setting::get($key);
            if ($v === null) {
                continue;
            }
            $set[$path] = self::cast($v, $type);
        }
        if (isset($set['mail.mailers.smtp.host'])) {
            $set['mail.default'] = 'smtp';
        }
        if (is_file(self::rcloneConfig())) {
            $set['indexer.backup.rclone_config'] = self::rcloneConfig();
        }
        if ($set) {
            config($set);
        }
    }

    public static function cast(string $v, string $type): mixed
    {
        return match (true) {
            $type === 'bool' => $v === '1',
            $type === 'port', str_starts_with($type, 'int') => (int) $v,
            $type === 'secret' => self::decrypt($v),
            default => $v,
        };
    }

    public static function decrypt(string $v): ?string
    {
        try {
            return Crypt::decryptString($v);
        } catch (\Throwable $e) {
            return null; // APP_KEY geändert: Wert unbrauchbar
        }
    }

    public static function rcloneConfig(): string
    {
        return storage_path('rclone/rclone.conf');
    }
}
