<?php

namespace Tests\Unit;

use App\Support\SystemStats;
use PHPUnit\Framework\TestCase;

class SystemStatsTest extends TestCase
{
    public function test_cpu_prozent_aus_zwei_messungen(): void
    {
        $a = SystemStats::parseCpu("cpu  100 0 100 700 100 0 0 0 0 0\ncpu0 50 0 50 350 50 0 0 0 0 0\n");
        $b = SystemStats::parseCpu("cpu  150 0 150 750 150 0 0 0 0 0\ncpu0 75 0 75 375 75 0 0 0 0 0\n");
        // busy +100, idle+iowait +100 => 50 %
        $this->assertSame(50.0, SystemStats::cpuPercent($a, $b));
    }

    public function test_cpu_ohne_veraenderung_ist_null(): void
    {
        $a = SystemStats::parseCpu("cpu  1 2 3 4 5 6 7 8 0 0\n");
        $this->assertNull(SystemStats::cpuPercent($a, $a));
        $this->assertNull(SystemStats::parseCpu('kaputt'));
    }

    public function test_ram_nutzt_mem_available(): void
    {
        $m = SystemStats::parseMeminfo("MemTotal:        4000000 kB\nMemFree:          500000 kB\nMemAvailable:    3000000 kB\nSwapTotal:             0 kB\n");
        $u = SystemStats::usage($m['MemTotal'], $m['MemTotal'] - $m['MemAvailable']);
        $this->assertSame(25.0, $u['percent']);
        $this->assertSame(4000000 * 1024, $u['total']);
    }

    public function test_load_und_grenzen(): void
    {
        $this->assertSame([0.52, 0.4, 0.31], SystemStats::parseLoadavg("0.52 0.40 0.31 1/234 5678\n"));
        $this->assertNull(SystemStats::parseLoadavg(null));
        $this->assertNull(SystemStats::usage(0, 5));
        $this->assertSame(100.0, SystemStats::usage(10, 50)['percent']);
    }

    public function test_echte_werte_auf_linux(): void
    {
        if (! is_readable('/proc/stat')) {
            $this->markTestSkipped('kein /proc');
        }
        $s = SystemStats::collect('/');
        $this->assertNotNull($s['ram']);
        $this->assertGreaterThan(0, $s['cores']);
        $this->assertNotNull($s['disk']);
    }
}
