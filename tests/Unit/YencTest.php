<?php
namespace Tests\Unit;

use App\Services\Yenc;
use PHPUnit\Framework\TestCase;

class YencTest extends TestCase
{
    private function encode(string $data): array
    {
        $out = '';
        foreach (str_split($data) as $ch) {
            $c = (ord($ch) + 42) & 255;
            if (in_array($c, [0, 10, 13, 61], true)) {
                $out .= '=' . chr(($c + 64) & 255);
            } else {
                $out .= chr($c);
            }
        }
        return ['=ybegin part=1 line=128 size=' . strlen($data) . " name=x.bin\r\n", "=ypart begin=1 end=" . strlen($data) . "\r\n", $out . "\r\n", "=yend size=" . strlen($data) . "\r\n"];
    }

    public function test_roundtrip_all_bytes(): void
    {
        $data = '';
        for ($i = 0; $i < 256; $i++) {
            $data .= chr($i);
        }
        $this->assertSame($data, Yenc::decode($this->encode($data)));
    }

    public function test_limit(): void
    {
        $this->assertSame('Rar!', Yenc::decode($this->encode("Rar!\x1A\x07\x00more data"), 4));
    }
}
