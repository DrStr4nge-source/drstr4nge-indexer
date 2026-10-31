<?php
namespace Tests\Unit;

use App\Services\Par2;
use PHPUnit\Framework\TestCase;

class Par2Test extends TestCase
{
    private function packet(string $type, string $body): string
    {
        $len = 64 + strlen($body);
        return "PAR2\0PKT" . pack('P', $len) . str_repeat("\1", 16) . str_repeat("\2", 16) . $type . $body;
    }

    private function fileDesc(string $name, int $size): string
    {
        $padded = $name . str_repeat("\0", (4 - strlen($name) % 4) % 4);
        return $this->packet("PAR 2.0\0FileDesc", str_repeat("\3", 16) . str_repeat("\4", 16) . str_repeat("\5", 16) . pack('P', $size) . $padded);
    }

    public function test_reads_names_and_picks_release_name(): void
    {
        $bin = $this->packet("PAR 2.0\0Main\0\0\0\0", str_repeat("\0", 12))
            . $this->fileDesc('Show.Name.S01E02.1080p.WEB.h264-GRP.mkv', 900_000_000)
            . $this->fileDesc('Show.Name.S01E02.1080p.WEB.h264-GRP.nfo', 2000)
            . $this->fileDesc('sample.mkv', 5_000_000);
        $names = Par2::fileNames($bin);
        $this->assertCount(3, $names);
        $this->assertSame('Show.Name.S01E02.1080p.WEB.h264-GRP', Par2::releaseName($names));
    }

    public function test_rar_parts_are_stripped(): void
    {
        $names = Par2::fileNames($this->fileDesc('Cool.Movie.2020.1080p.BluRay-GRP.part01.rar', 50_000_000));
        $this->assertSame('Cool.Movie.2020.1080p.BluRay-GRP', Par2::releaseName($names));
    }

    public function test_garbage_and_truncated_input(): void
    {
        $this->assertSame([], Par2::fileNames('not a par2 file'));
        $this->assertSame([], Par2::fileNames(substr($this->fileDesc('a.mkv', 1), 0, 90)));
    }
}
