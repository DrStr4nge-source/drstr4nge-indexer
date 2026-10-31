<?php
namespace Tests\Unit;

use App\Services\RarInspector;
use PHPUnit\Framework\TestCase;

class RarInspectorTest extends TestCase
{
    private function rar4(int $mainFlags, int $fileFlags): string
    {
        $main = "\0\0" . chr(0x73) . pack('v', $mainFlags) . pack('v', 13) . str_repeat("\0", 6);
        $file = "\0\0" . chr(0x74) . pack('v', $fileFlags) . pack('v', 32) . str_repeat("\0", 25);
        return "Rar!\x1A\x07\x00" . $main . $file;
    }

    public function test_rar4(): void
    {
        $this->assertFalse(RarInspector::isEncrypted($this->rar4(0, 0)));
        $this->assertTrue(RarInspector::isEncrypted($this->rar4(0, 0x0004)));
        $this->assertTrue(RarInspector::isEncrypted($this->rar4(0x0080, 0)));
    }

    public function test_rar5(): void
    {
        $sig = "Rar!\x1A\x07\x01\x00";
        $crc = "\0\0\0\0";
        $main = $crc . "\x03\x01\x00\x00";
        $plain = $sig . $main . $crc . "\x08\x02\x00\x00\x00\x00\x00\x00\x00";
        $this->assertFalse(RarInspector::isEncrypted($plain));

        $withEnc = $sig . $main . $crc . "\x0C\x02\x01\x03\x00\x00\x00\x00\x00\x00" . "\x02\x01\x00";
        $this->assertTrue(RarInspector::isEncrypted($withEnc));

        $headerEnc = $sig . $crc . "\x02\x04\x00";
        $this->assertTrue(RarInspector::isEncrypted($headerEnc));
    }

    public function test_unknown(): void
    {
        $this->assertNull(RarInspector::isEncrypted('PK\x03\x04zip'));
        $this->assertNull(RarInspector::isEncrypted(''));
    }
}
