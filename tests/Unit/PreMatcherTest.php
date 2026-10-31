<?php

namespace Tests\Unit;

use App\Services\Pre\PreMatcher;
use PHPUnit\Framework\TestCase;

class PreMatcherTest extends TestCase
{
    private const GB = 1024 ** 3;

    public function test_genau_aus_par2(): void
    {
        $m = PreMatcher::measure(['real_size' => (int) (4.37 * self::GB) + 3_000_000, 'real_files' => 46, 'files' => []]);
        $this->assertTrue($m['exact']);
        $c = [
            ['id' => 1, 'size' => (int) round(4.37 * self::GB), 'files' => 47, 'title' => 'Richtig-GRP'],
            ['id' => 2, 'size' => (int) round(4.50 * self::GB), 'files' => 47, 'title' => 'Zu.Gross-GRP'],
            ['id' => 3, 'size' => (int) round(4.37 * self::GB), 'files' => 90, 'title' => 'Andere.Dateizahl-GRP'],
        ];
        $this->assertSame('Richtig-GRP', PreMatcher::pick($m, $c)['title']);
    }

    public function test_zwei_passende_heisst_keiner(): void
    {
        $m = ['size' => 2 * self::GB, 'files' => 20, 'exact' => true];
        $c = [['id' => 1, 'size' => 2 * self::GB, 'files' => 21, 'title' => 'A'], ['id' => 2, 'size' => 2 * self::GB + 1000, 'files' => 20, 'title' => 'B']];
        $this->assertNull(PreMatcher::pick($m, $c));
    }

    public function test_geschaetzt_aus_usenet(): void
    {
        $files = [];
        for ($i = 1; $i <= 10; $i++) {
            $files[sprintf('a8f7d6.part%02d.rar', $i)] = ['segs' => [['bytes' => (int) (100 * 1024 * 1024 * PreMatcher::YENC_OVERHEAD)]], 'total' => 1];
        }
        $files['a8f7d6.par2'] = ['segs' => [['bytes' => 50000]], 'total' => 1];
        $files['a8f7d6.vol00+01.par2'] = ['segs' => [['bytes' => 9_000_000]], 'total' => 1];
        $m = PreMatcher::measure(['files' => $files]);
        $this->assertFalse($m['exact']);
        $this->assertSame(10, $m['files']);
        $this->assertSame(1000 * 1024 * 1024, $m['size']);
        $c = [['id' => 1, 'size' => 1000 * 1024 * 1024, 'files' => 10, 'title' => 'X-GRP'], ['id' => 2, 'size' => 1000 * 1024 * 1024, 'files' => null, 'title' => 'OhneZahl']];
        $this->assertSame('X-GRP', PreMatcher::pick($m, $c)['title']); // geschätzt: ohne Dateizahl kein Treffer
        // Namen ohne Endung: nicht bestimmbar
        $this->assertNull(PreMatcher::measure(['files' => ['8a7sd6f87as6df' => ['segs' => [['bytes' => 1]], 'total' => 1]]]));
    }
}
