<?php
namespace App\Services;

/** Minimaler NNTP-Client (nur das, was der Crawler braucht). */
class Nntp
{
    /** @var resource */
    private $sock;

    /** @param int $timeout Sekunden für Verbindungsaufbau und jede Antwort */
    public function connect(int $timeout = 30): void
    {
        $c = config('indexer.nntp');
        if (!$c['host']) {
            throw new \RuntimeException('NNTP_HOST ist nicht gesetzt (.env).');
        }
        $this->sock = @stream_socket_client(
            ($c['ssl'] ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'],
            $errno, $errstr, $timeout
        );
        if (!$this->sock) {
            throw new \RuntimeException("NNTP-Verbindung fehlgeschlagen: $errstr");
        }
        stream_set_timeout($this->sock, $timeout); // kurz warten, solange Begrüßung/Login laufen
        $this->expect($this->readLine(), [200, 201]);

        if ($c['user']) {
            $r = $this->cmd('AUTHINFO USER ' . $c['user']);
            if ($this->code($r) === 381) {
                $r = $this->cmd('AUTHINFO PASS ' . $c['pass']);
            }
            $this->expect($r, [281]);
        }
        stream_set_timeout($this->sock, max($timeout, 120)); // große Header-Abrufe dürfen länger dauern
    }

    /** @return array{0:int,1:int} [niedrigste, höchste Artikelnummer] */
    public function group(string $name): array
    {
        $r = $this->cmd("GROUP $name");
        $this->expect($r, [211]);
        $p = explode(' ', trim($r));
        return [(int) $p[2], (int) $p[3]];
    }

    /** @return array<int,array> Zeilen: [nr, subject, from, date, msgid, refs, bytes, lines] */
    public function xover(int $from, int $to): array
    {
        $r = $this->cmd("XOVER $from-$to");
        if (in_array($this->code((string) $r), [420, 423], true)) {
            return []; // keine Artikel in diesem Bereich (Lücke in der Gruppe): weiterlesen statt hängen bleiben
        }
        $this->expect($r, [224]);
        $rows = [];
        while (true) {
            // Bricht die Verbindung mitten in der Liste ab, wirft readLine(). So wird nie ein halber Block gespeichert.
            $line = rtrim($this->readLine(), "\r\n");
            if ($line === '.') {
                break;
            }
            $rows[] = explode("\t", $line);
        }
        return $rows;
    }

    /**
     * Holt den Rohtext eines Artikels per Message-ID.
     * @return array<int,string>|null null = Artikel existiert nicht mehr (430/423/420)
     */
    public function body(string $messageId): ?array
    {
        $r = $this->cmd('BODY <' . trim($messageId, '<>') . '>');
        $code = $this->code($r);
        if (in_array($code, [430, 423, 420], true)) {
            return null;
        }
        $this->expect($r, [222]);
        $lines = [];
        while (true) {
            $line = $this->readLine();
            if (rtrim($line, "\r\n") === '.') {
                break;
            }
            if (str_starts_with($line, '..')) { // Dot-Stuffing entfernen
                $line = substr($line, 1);
            }
            $lines[] = $line;
        }
        return $lines;
    }

    public function close(): void
    {
        if ($this->sock) {
            @fwrite($this->sock, "QUIT\r\n");
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    private function cmd(string $line): string
    {
        $line = str_replace(["\r", "\n", "\0"], '', $line); // nie zwei Befehle in einer Zeile
        if (!$this->sock || @fwrite($this->sock, $line . "\r\n") === false) {
            throw new NntpConnectionLost('Verbindung zum Usenet-Server getrennt (beim Senden)');
        }
        return $this->readLine();
    }

    /** Eine Zeile lesen. Getrennte Verbindung oder Zeitüberschreitung => NntpConnectionLost statt PHP-Warnung. */
    private function readLine(): string
    {
        $line = $this->sock ? @fgets($this->sock) : false;
        if ($line === false) {
            $meta = $this->sock ? @stream_get_meta_data($this->sock) : [];
            throw new NntpConnectionLost(!empty($meta['timed_out'])
                ? 'Usenet-Server antwortet nicht mehr (Zeitüberschreitung)'
                : 'Verbindung vom Usenet-Server getrennt');
        }
        return $line;
    }

    private function code(string $resp): int
    {
        return (int) substr($resp, 0, 3);
    }

    private function expect(string|false $resp, array $codes): void
    {
        if ($resp === false || !in_array($this->code($resp), $codes, true)) {
            throw new \RuntimeException('Unerwartete NNTP-Antwort: ' . trim((string) $resp));
        }
    }
}
