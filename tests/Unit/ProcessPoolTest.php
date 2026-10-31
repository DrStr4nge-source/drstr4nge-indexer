<?php
namespace Tests\Unit;

use App\Support\ProcessPool;
use PHPUnit\Framework\TestCase;

class ProcessPoolTest extends TestCase
{
    private function job(string $code): array
    {
        return [PHP_BINARY, '-r', $code];
    }

    public function test_runs_in_parallel_but_respects_limit(): void
    {
        $dir = sys_get_temp_dir() . '/pp' . getmypid() . mt_rand();
        mkdir($dir);
        // Jeder Job legt beim Start eine Datei an, zählt die gleichzeitig laufenden und schläft kurz
        mkdir("$dir/run");
        $code = 'touch($argv[1]."/run/".getmypid()); usleep(150000); $n = count(glob($argv[1]."/run/*")); $f = fopen($argv[1]."/max", "c+"); flock($f, LOCK_EX); $m = (int) stream_get_contents($f); ftruncate($f, 0); rewind($f); fwrite($f, (string) max($m, $n)); flock($f, LOCK_UN); fclose($f); usleep(600000); unlink($argv[1]."/run/".getmypid()); echo "fertig\n";';
        $jobs = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $n) {
            $jobs[$n] = [PHP_BINARY, '-r', $code, $dir];
        }
        $lines = [];
        $t = microtime(true);
        $codes = ProcessPool::run($jobs, 2, function ($name, $line) use (&$lines) { $lines[] = "$name:$line"; });
        $dt = microtime(true) - $t;
        $max = (int) file_get_contents("$dir/max");
        @unlink("$dir/max");
        @rmdir("$dir/run");
        @rmdir($dir);

        $this->assertSame(['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0, 'e' => 0], $this->sorted($codes));
        $this->assertTrue($max <= 2, "höchstens 2 gleichzeitig, waren $max");
        $this->assertSame(2, $max, 'es soll wirklich parallel laufen');
        $this->assertTrue($dt < 3.2, sprintf('parallel schneller als nacheinander (%.1fs)', $dt));
        $this->assertCount(5, $lines);
    }

    public function test_exit_codes_stderr_and_partial_lines(): void
    {
        $got = [];
        $codes = ProcessPool::run([
            'ok' => $this->job('echo "eins\nzwei\n"; echo "ohne Zeilenende";'),
            'kaputt' => $this->job('fwrite(STDERR, "Fehler!\n"); exit(3);'),
            'gross' => $this->job('echo str_repeat("x", 200000), "\nende\n";'),
        ], 3, function ($name, $line, $err) use (&$got) { $got[$name][] = ($err ? 'E:' : '') . (strlen($line) > 50 ? 'LANG' . strlen($line) : $line); });

        $this->assertSame(0, $codes['ok']);
        $this->assertSame(3, $codes['kaputt']);
        $this->assertSame(0, $codes['gross']);
        $this->assertSame(['eins', 'zwei', 'ohne Zeilenende'], $got['ok']);
        $this->assertSame(['E:Fehler!'], $got['kaputt']);
        $this->assertSame(['LANG200000', 'ende'], $got['gross']);
    }

    public function test_empty_and_limit_one(): void
    {
        $this->assertSame([], ProcessPool::run([], 4, fn () => null));
        $order = [];
        ProcessPool::run(['x' => $this->job('usleep(200000); echo "x\n";'), 'y' => $this->job('echo "y\n";')], 1,
            function ($n, $l) use (&$order) { $order[] = $l; });
        $this->assertSame(['x', 'y'], $order, 'mit Limit 1 strikt nacheinander');
    }

    private function sorted(array $a): array
    {
        ksort($a);
        return $a;
    }
}
