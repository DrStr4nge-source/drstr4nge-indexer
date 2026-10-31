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
        $this->expect(fgets($this->sock), [200, 201]);

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
        $this->expect($this->cmd("XOVER $from-$to"), [224]);
        $rows = [];
        while (($line = fgets($this->sock)) !== false) {
            $line = rtrim($line, "\r\n");
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
        while (($line = fgets($this->sock)) !== false) {
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
        }
    }

    private function cmd(string $line): string
    {
        fwrite($this->sock, $line . "\r\n");
        return (string) fgets($this->sock);
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
