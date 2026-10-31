<?php
namespace App\Support;

/**
 * Startet Befehle als eigene Prozesse, höchstens $max gleichzeitig. Reines PHP (proc_open), ohne Shell.
 * Jede Ausgabezeile wird mit dem Job-Namen an $onLine gegeben.
 */
class ProcessPool
{
    /**
     * @param array<string,string[]> $jobs Name => Befehl als Liste (z.B. [PHP_BINARY, 'artisan', ...])
     * @param callable(string $name, string $line, bool $isError):void $onLine
     * @return array<string,int> Exit-Code je Job
     */
    public static function run(array $jobs, int $max, callable $onLine, ?string $cwd = null): array
    {
        $max = max(1, $max);
        $queue = $jobs;
        $running = [];
        $codes = [];

        while ($queue || $running) {
            while ($queue && count($running) < $max) {
                $name = array_key_first($queue);
                $cmd = array_shift($queue);
                $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
                if (!is_resource($proc)) {
                    $onLine($name, 'Prozess konnte nicht gestartet werden.', true);
                    $codes[$name] = 127;
                    continue;
                }
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $running[$name] = ['proc' => $proc, 'out' => $pipes[1], 'err' => $pipes[2], 'buf' => ['', '']];
            }

            foreach ($running as $name => &$r) {
                self::drain($name, $r, $onLine, false);
                $status = proc_get_status($r['proc']);
                if (!$status['running']) {
                    self::drain($name, $r, $onLine, true);
                    fclose($r['out']);
                    fclose($r['err']);
                    $close = proc_close($r['proc']);
                    // exitcode ist nur beim ersten "nicht mehr läuft" gültig
                    $codes[$name] = $status['exitcode'] >= 0 ? $status['exitcode'] : $close;
                    unset($running[$name]);
                }
            }
            unset($r);
            if ($running) {
                usleep(100_000);
            }
        }
        return $codes;
    }

    private static function drain(string $name, array &$r, callable $onLine, bool $final): void
    {
        foreach (['out' => 0, 'err' => 1] as $key => $i) {
            while (($chunk = fread($r[$key], 65536)) !== false && $chunk !== '') {
                $r['buf'][$i] .= $chunk;
            }
            $lines = explode("\n", $r['buf'][$i]);
            $r['buf'][$i] = $final ? '' : array_pop($lines);
            foreach ($lines as $l) {
                $l = rtrim($l, "\r");
                if ($l !== '') {
                    $onLine($name, $l, $i === 1);
                }
            }
        }
    }
}
