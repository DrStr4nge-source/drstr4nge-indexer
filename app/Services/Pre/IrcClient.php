<?php
namespace App\Services\Pre;

/**
 * Schlanker IRC-Client: verbinden (optional TLS), anmelden, Kanäle betreten,
 * auf PING antworten und Kanal-Nachrichten weiterreichen.
 */
class IrcClient
{
    /** @var resource|null */
    private $sock = null;
    private string $nick;
    private int $lastData = 0;
    private bool $pinged = false;

    /** @param callable(string $msg):void $log */
    public function __construct(private array $cfg, private $log = null)
    {
        $this->nick = (string) $cfg['nick'];
    }

    public function connect(): void
    {
        $c = $this->cfg;
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => (bool) ($c['tls_verify'] ?? true),
            'verify_peer_name' => (bool) ($c['tls_verify'] ?? true),
            'SNI_enabled' => true,
        ]]);
        $this->sock = @stream_socket_client(
            ($c['tls'] ? 'tls://' : 'tcp://') . $c['server'] . ':' . (int) $c['port'],
            $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$this->sock) {
            throw new \RuntimeException("Verbindung zu {$c['server']}:{$c['port']} fehlgeschlagen: $errstr");
        }
        stream_set_timeout($this->sock, 30);
        if (!empty($c['password'])) {
            $this->send('PASS ' . $c['password']);
        }
        $this->send('NICK ' . $this->nick);
        $this->send('USER ' . ($c['username'] ?: $this->nick) . ' 0 * :' . ($c['realname'] ?: $this->nick));

        // Warten auf 001 (angemeldet)
        $deadline = time() + 90;
        while (time() < $deadline) {
            $line = $this->readLine();
            if ($line === null) {
                continue;
            }
            $msg = self::parse($line);
            if ($msg['command'] === '001') {
                $this->nick = $msg['params'][0] ?? $this->nick;
                stream_set_timeout($this->sock, 10); // kurz, damit $tick regelmäßig läuft
                return;
            }
            if (in_array($msg['command'], ['432', '433', '436'], true)) { // Nick ungültig/vergeben
                $this->nick = substr(preg_replace('/[^A-Za-z0-9_\-\[\]\\\\`^{}|]/', '', (string) $this->cfg['nick']), 0, 12) . random_int(10, 99);
                $this->log("Nick vergeben, versuche {$this->nick}");
                $this->send('NICK ' . $this->nick);
            } elseif ($msg['command'] === 'ERROR') {
                throw new \RuntimeException('Server lehnt ab: ' . ($msg['trailing'] ?? $line));
            } elseif (in_array($msg['command'], ['464', '465'], true)) {
                throw new \RuntimeException('Anmeldung abgelehnt (' . $msg['command'] . '): ' . ($msg['trailing'] ?? ''));
            }
        }
        throw new \RuntimeException('Keine Anmeldebestätigung vom IRC-Server (001) innerhalb von 90 s');
    }

    public function join(array $channels): void
    {
        foreach ($channels as $ch) {
            $parts = preg_split('/\s+/', trim($ch), 2); // "#kanal schlüssel"
            $this->send('JOIN ' . $parts[0] . (isset($parts[1]) ? ' ' . $parts[1] : ''));
        }
    }

    /**
     * Liest, bis die Verbindung abbricht.
     * @param callable(string $from, string $channel, string $text):void $onMessage
     * @param callable():void|null $tick wird etwa alle 10 s aufgerufen (darf werfen, z.B. IrcReload)
     */
    public function listen(callable $onMessage, int $idle = 300, ?callable $tick = null): void
    {
        $this->lastData = time();
        $lastTick = time();
        while (true) {
            if ($tick && time() - $lastTick >= 10) {
                $lastTick = time();
                $tick();
            }
            $line = $this->readLine();
            if ($line === null) {
                // Länger nichts gehört: selbst PING senden, danach aufgeben
                if (time() - $this->lastData > $idle) {
                    if ($this->pinged) {
                        throw new \RuntimeException("Keine Daten seit {$idle} s, Verbindung tot");
                    }
                    $this->send('PING :' . $this->cfg['server']);
                    $this->pinged = true;
                    $this->lastData = time() - $idle + 60;
                }
                continue;
            }
            $msg = self::parse($line);
            switch ($msg['command']) {
                case 'PRIVMSG':
                case 'NOTICE':
                    $target = $msg['params'][0] ?? '';
                    if ($target !== '' && $target[0] === '#') {
                        $onMessage($msg['nick'], $target, (string) $msg['trailing']);
                    }
                    break;
                case 'JOIN':
                    if (strcasecmp($msg['nick'], $this->nick) === 0) {
                        $this->log('Kanal betreten: ' . ($msg['trailing'] ?: ($msg['params'][0] ?? '')));
                    }
                    break;
                case 'KICK':
                    if (strcasecmp($msg['params'][1] ?? '', $this->nick) === 0) {
                        $this->log("Aus {$msg['params'][0]} gekickt: {$msg['trailing']}, betrete neu in 30 s");
                        sleep(30);
                        $this->send('JOIN ' . $msg['params'][0]);
                    }
                    break;
                case '471': case '473': case '474': case '475': case '477':
                    $this->log('Kanal ' . ($msg['params'][1] ?? '?') . ' nicht betretbar: ' . $msg['trailing']);
                    break;
                case 'ERROR':
                    throw new \RuntimeException('Server trennt: ' . $msg['trailing']);
            }
        }
    }

    public function close(): void
    {
        if ($this->sock) {
            @fwrite($this->sock, "QUIT :bye\r\n");
            @fclose($this->sock);
        }
        $this->sock = null;
    }

    /** Eine Zeile lesen; PING sofort beantworten. null = gerade nichts da (Timeout). */
    private function readLine(): ?string
    {
        $line = @fgets($this->sock, 4096);
        if ($line === false) {
            $meta = @stream_get_meta_data($this->sock);
            if (!empty($meta['timed_out'])) {
                return null;
            }
            throw new \RuntimeException('Verbindung zum IRC-Server getrennt');
        }
        $this->lastData = time();
        $this->pinged = false;
        $line = rtrim($line, "\r\n");
        if (str_starts_with($line, 'PING')) {
            $this->send('PONG' . substr($line, 4));
            return null;
        }
        return $line;
    }

    private function send(string $line): void
    {
        $line = str_replace(["\r", "\n", "\0"], '', $line); // Zeilenumbruch aus Einstellungen darf keinen zweiten IRC-Befehl ergeben
        if (!$this->sock || @fwrite($this->sock, $line . "\r\n") === false) {
            throw new \RuntimeException('Verbindung zum IRC-Server getrennt (beim Senden)');
        }
    }

    private function log(string $m): void
    {
        if ($this->log) {
            ($this->log)($m);
        }
    }

    /** ":nick!user@host CMD p1 p2 :trailing" zerlegen */
    public static function parse(string $line): array
    {
        $prefix = '';
        if (str_starts_with($line, ':')) {
            [$prefix, $line] = array_pad(explode(' ', substr($line, 1), 2), 2, '');
        }
        $trailing = null;
        if (($p = strpos($line, ' :')) !== false) {
            $trailing = substr($line, $p + 2);
            $line = substr($line, 0, $p);
        }
        $parts = preg_split('/ +/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        return [
            'nick' => explode('!', $prefix, 2)[0],
            'command' => strtoupper(array_shift($parts) ?? ''),
            'params' => $parts,
            'trailing' => $trailing,
        ];
    }
}
