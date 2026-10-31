<?php
namespace App\Console\Commands;

use App\Models\Newsgroup;
use App\Services\Nntp;
use Illuminate\Console\Command;

/** Prüft Verbindung, Login und Gruppenzugriff beim Usenet-Provider und erklärt Fehler verständlich. */
class TestNntp extends Command
{
    protected $signature = 'indexer:test-nntp {group? : Newsgroup zum Testen (Standard: erste aktive Gruppe oder alt.binaries.test)}';
    protected $description = 'Testet die Verbindung zum Usenet-Provider (Server, Login, Gruppe)';

    public function handle(Nntp $nntp): int
    {
        $c = config('indexer.nntp');
        $this->line('');
        $this->line('Einstellungen aus der .env:');
        $this->line('  Server:   ' . ($c['host'] ?: '(fehlt)'));
        $this->line('  Port:     ' . $c['port']);
        $this->line('  SSL:      ' . ($c['ssl'] ? 'ja' : 'nein'));
        $this->line('  Benutzer: ' . ($c['user'] ?: '(keiner)'));
        $this->line('  Passwort: ' . ($c['pass'] ? 'gesetzt' : '(fehlt)'));
        $this->line('  Verbindungen Crawler: ' . max(1, (int) ($c['connections'] ?? 1)) . ' (+1 für die Prüfung)');
        $this->line('');

        if (!$c['host']) {
            $this->error('NNTP_HOST fehlt.');
            $this->line(self::hint('NNTP_HOST ist nicht gesetzt', $c));
            return self::FAILURE;
        }
        if ($c['user'] && !$c['pass']) {
            $this->warn('NNTP_USER ist gesetzt, aber NNTP_PASS fehlt.');
        }

        // 1) Verbindung und Login
        $t = microtime(true);
        try {
            $nntp->connect(15);
        } catch (\RuntimeException $e) {
            $this->error('Verbindung/Login fehlgeschlagen: ' . $e->getMessage());
            $this->line(self::hint($e->getMessage(), $c));
            return self::FAILURE;
        }
        $this->info(sprintf('OK  Verbindung und Login (%d ms)', (microtime(true) - $t) * 1000));

        // 2) Gruppe auswählen
        $group = $this->argument('group') ?: $this->firstGroup();
        try {
            [$low, $high] = $nntp->group($group);
        } catch (\RuntimeException $e) {
            $this->error("Gruppe \"$group\" nicht nutzbar: " . $e->getMessage());
            $this->line(self::hint($e->getMessage(), $c));
            $nntp->close();
            return self::FAILURE;
        }
        $count = $high >= $low ? $high - $low + 1 : 0;
        $this->info(sprintf('OK  Gruppe %s: Artikel %s bis %s (etwa %s)', $group,
            number_format($low, 0, ',', '.'), number_format($high, 0, ',', '.'), number_format($count, 0, ',', '.')));

        // 3) Header lesen (das, was der Crawler macht)
        if ($count > 0) {
            try {
                $rows = $nntp->xover(max($low, $high - 9), $high);
                $this->info('OK  Header lesen: ' . count($rows) . ' Header der neuesten Artikel geholt');
            } catch (\RuntimeException $e) {
                $this->error('Header lesen fehlgeschlagen: ' . $e->getMessage());
                $this->line(self::hint($e->getMessage(), $c));
                $nntp->close();
                return self::FAILURE;
            }
        } else {
            $this->warn('Die Gruppe ist leer, Header-Test übersprungen.');
        }

        $nntp->close();
        $this->line('');
        $this->info('Alles OK. Der Crawler kann loslegen: php artisan indexer:crawl');
        return self::SUCCESS;
    }

    private function firstGroup(): string
    {
        try {
            return Newsgroup::where('active', true)->orderBy('id')->value('name') ?: 'alt.binaries.test';
        } catch (\Throwable $e) {
            return 'alt.binaries.test';
        }
    }

    /** Übersetzt Fehlermeldungen in einen verständlichen Hinweis. */
    public static function hint(string $msg, array $c): string
    {
        $m = strtolower($msg);
        $code = preg_match('/antwort:\s*(\d{3})/', $m, $x) ? (int) $x[1] : null;

        if (str_contains($m, 'nicht gesetzt')) {
            return 'Hinweis: NNTP_HOST in der .env eintragen (z.B. news.example.com), danach: php artisan config:clear';
        }
        if (str_contains($m, 'verbindung fehlgeschlagen')) {
            if (preg_match('/getaddrinfo|name or service not known|no address|php_network_getaddresses|temporary failure in name resolution/', $m)) {
                return 'Hinweis: Der Servername wurde nicht gefunden. NNTP_HOST auf Tippfehler prüfen.';
            }
            if (str_contains($m, 'refused')) {
                return 'Hinweis: Der Server lehnt den Port ab. Üblich sind 563 (mit SSL) oder 119 (ohne SSL). NNTP_PORT prüfen.';
            }
            if (str_contains($m, 'timed out') || str_contains($m, 'zeitüberschreitung')) {
                return 'Hinweis: Keine Antwort vom Server. Port falsch oder eine Firewall blockiert ausgehende Verbindungen auf diesem Port.';
            }
            if (str_contains($m, 'ssl') || str_contains($m, 'tls') || str_contains($m, 'certificate')) {
                return 'Hinweis: SSL passt nicht zum Port. Mit SSL meist Port 563, ohne SSL Port 119 (NNTP_SSL=true/false).';
            }
            return 'Hinweis: Server, Port und SSL in der .env prüfen (meist Port 563 mit NNTP_SSL=true).';
        }
        return match (true) {
            $code === 481 => 'Hinweis: Benutzername oder Passwort falsch (NNTP_USER, NNTP_PASS). Sonderzeichen im Passwort in Anführungszeichen setzen: NNTP_PASS="..."',
            $code === 480 => 'Hinweis: Der Server verlangt einen Login. NNTP_USER und NNTP_PASS eintragen.',
            $code === 482 => 'Hinweis: Login-Reihenfolge abgelehnt. Bitte Zugangsdaten beim Provider prüfen.',
            $code === 502 => 'Hinweis: Zugriff verweigert. Häufige Gründe: Konto abgelaufen, zu viele gleichzeitige Verbindungen oder falsche Zugangsdaten.',
            $code === 400 => 'Hinweis: Der Server ist gerade nicht verfügbar oder es sind zu viele Verbindungen offen. Später erneut versuchen.',
            $code === 411 => 'Hinweis: Diese Gruppe gibt es bei deinem Provider nicht. Namen prüfen oder eine andere Gruppe testen: php artisan indexer:test-nntp alt.binaries.xyz',
            $code === 420, $code === 423 => 'Hinweis: In diesem Bereich gibt es keine Artikel. Die Verbindung selbst funktioniert.',
            $code === null && str_contains($m, 'antwort:') => 'Hinweis: Der Server hat nicht geantwortet. Oft passt SSL nicht zum Port (Port 563 braucht NNTP_SSL=true).',
            default => 'Hinweis: Unbekannter Fehler. Die Meldung oben an den Provider-Support oder hier im Chat schicken.',
        };
    }
}
