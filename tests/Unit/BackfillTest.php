<?php
namespace Tests\Unit;

use App\Services\Backfill;
use PHPUnit\Framework\TestCase;

class BackfillTest extends TestCase
{
    /** Gruppe mit Artikeln $low..$high, Datum steigt gleichmäßig (plus Rauschen), Lücken möglich. */
    private function group(int $low, int $high, int $from, int $to, int $noise = 0, int $gapEvery = 0): callable
    {
        mt_srand(42);
        return function (int $n) use ($low, $high, $from, $to, $noise, $gapEvery, &$calls) {
            for ($a = $n; $a <= min($n + 200, $high); $a++) {
                if ($gapEvery && $a % $gapEvery < 50) {
                    continue; // Lücke
                }
                $ts = (int) ($from + ($a - $low) / max(1, $high - $low) * ($to - $from));
                return [$a, $ts + ($noise ? mt_rand(-$noise, $noise) : 0)];
            }
            return null;
        };
    }

    public function test_finds_article_of_cutoff_day(): void
    {
        $now = 1_800_000_000;
        $probe = $this->group(1, 1_000_000, $now - 100 * 86400, $now); // 100 Tage, 10.000 Artikel pro Tag
        $start = Backfill::findStart($probe, 1, 1_000_000, $now - 60 * 86400);
        // wahrer Start: Artikel 400.000; Genauigkeit 200 Artikel, lieber etwas früher
        $this->assertTrue($start <= 400_001 && $start >= 399_700, "Start $start");
    }

    public function test_noise_and_gaps(): void
    {
        $now = 1_800_000_000;
        $probe = $this->group(5_000_000, 9_000_000, $now - 200 * 86400, $now, 3600, 7919);
        $start = Backfill::findStart($probe, 5_000_000, 9_000_000, $now - 60 * 86400);
        $true = 5_000_000 + (int) (4_000_000 * 140 / 200); // 7.800.000
        $this->assertTrue(abs($start - $true) < 2_000, "Start $start, erwartet etwa $true");
    }

    public function test_group_younger_than_cutoff_starts_at_low(): void
    {
        $now = 1_800_000_000;
        $probe = $this->group(100, 5000, $now - 10 * 86400, $now);
        $this->assertSame(100, Backfill::findStart($probe, 100, 5000, $now - 60 * 86400));
    }

    public function test_group_older_than_cutoff_reads_only_the_end(): void
    {
        $now = 1_800_000_000;
        $probe = $this->group(1, 100_000, $now - 900 * 86400, $now - 100 * 86400);
        $start = Backfill::findStart($probe, 1, 100_000, $now - 60 * 86400);
        $this->assertTrue($start >= 99_700, "Start $start (nichts ist jünger als 60 Tage)");
    }

    public function test_unreadable_server_never_skips_articles(): void
    {
        $this->assertSame(1, Backfill::findStart(fn () => null, 1, 1_000_000, 1_700_000_000));
        $this->assertSame(10, Backfill::findStart(fn () => null, 10, 5, 1_700_000_000));
    }

    public function test_few_probes(): void
    {
        $now = 1_800_000_000;
        $calls = 0;
        $inner = $this->group(1, 50_000_000, $now - 3000 * 86400, $now);
        $probe = function ($n) use ($inner, &$calls) { $calls++; return $inner($n); };
        Backfill::findStart($probe, 1, 50_000_000, $now - 60 * 86400);
        $this->assertTrue($calls < 30, "$calls Abfragen");
    }
}
