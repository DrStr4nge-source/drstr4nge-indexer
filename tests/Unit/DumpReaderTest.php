<?php

namespace Tests\Unit;

use App\Models\Predb;
use App\Services\Pre\DumpReader;
use PHPUnit\Framework\TestCase;

class DumpReaderTest extends TestCase
{
    private function rows(string $text): array
    {
        $h = fopen('php://memory', 'r+');
        fwrite($h, $text);
        rewind($h);
        return iterator_to_array(DumpReader::read($h), false);
    }

    public function test_nzedb_format(): void
    {
        $l1 = "'Some.Show.S01E05.720p.BluRay.x264-GRP'\t\t\\N\t\t'2626MB'\t\t\\N\t\t''\t\t'0'\t\t\\N\t\t'X264'\t\t'2024-05-01 11:45:36'\t\t'xrel'\t\t'0'\t\t\\N\r\n";
        $l2 = "'Film.2023.German.DL.1080p.WEB.h264-ABC'\t\t\\N\t\t'5790MB'\t\t'61'\t\t''\t\t'2'\t\t'dupe.2023-01-01'\t\t'Movie HD-1080p'\t\t'2023-04-30 08:12:25'\t\t'xrelp2p'\t\t'0'\t\t\\N\r\n";
        $l3 = "'kurz'\t\t\\N\t\t\\N\t\t\\N\t\t''\t\t'0'\t\t\\N\t\t\\N\t\t'2024-01-01 00:00:00'\t\t'x'\t\t'0'\t\t\\N\r\n";
        $r = $this->rows($l1 . $l2 . $l3);
        $this->assertCount(2, $r); // zu kurzer Titel fällt weg
        $this->assertSame('Some.Show.S01E05.720p.BluRay.x264-GRP', $r[0]['title']);
        $this->assertSame(2626 * 1024 * 1024, $r[0]['size']);
        $this->assertSame('2024-05-01 11:45:36', $r[0]['predate']);
        $this->assertSame('xrel', $r[0]['source']);
        $this->assertSame(Predb::NONE, $r[0]['nuked']);
        $this->assertSame(61, $r[1]['files']);
        $this->assertSame(Predb::NUKED, $r[1]['nuked']);       // nZEDb 2 = nuked
        $this->assertSame('dupe.2023-01-01', $r[1]['nuke_reason']);
    }

    public function test_csv_mit_kopfzeile(): void
    {
        $r = $this->rows("release;section;size;files;pretime;nuke;reason\nA.Movie.2020.1080p-XYZ;X264;4500;50;1600000000;0;\nB.Show.S01E01-TV;TV;350MB;20;2021-02-03 04:05:06;1;bad.ar\n");
        $this->assertCount(2, $r);
        $this->assertSame(4500 * 1024 * 1024, $r[0]['size']);   // reine Zahl = MB
        $this->assertSame(gmdate('Y-m-d H:i:s', 1600000000), $r[0]['predate']);
        $this->assertSame(Predb::NUKED, $r[1]['nuked']);
        $this->assertSame('bad.ar', $r[1]['nuke_reason']);
        $this->assertSame('TV', $r[1]['category']);
    }
}
