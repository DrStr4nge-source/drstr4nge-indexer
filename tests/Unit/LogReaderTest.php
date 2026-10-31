<?php

namespace Tests\Unit;

use App\Support\LogReader;
use PHPUnit\Framework\TestCase;

class LogReaderTest extends TestCase
{
    private const LOG = <<<'LOG'
[2026-10-31 14:40:00] production.INFO: Pre-Auflösung: Release 5 -> X-GRP
[2026-10-31 14:43:12] production.ERROR: fgets(): SSL: Connection reset by peer {"exception":"[object] (ErrorException(code: 0): fgets(): SSL: Connection reset by peer at /var/www/indexer/app/Services/Nntp.php:97)
[stacktrace]
#0 /var/www/indexer/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(265): handleError()
#1 {main}
"}
[2026-10-31 15:00:00] production.WARNING: Crawler: alt.binaries.test: Verbindung vom Usenet-Server getrennt
LOG;

    public function test_eintraege_und_stufen(): void
    {
        $e = LogReader::parse(self::LOG);
        $this->assertCount(2, $e); // INFO fällt bei "Warnungen und Fehler" weg
        $this->assertSame('WARNING', $e[0]['level']);                 // neueste zuerst
        $this->assertSame('ERROR', $e[1]['level']);
        $this->assertSame('fgets(): SSL: Connection reset by peer', $e[1]['message']);
        $this->assertStringContainsString('#0 /var/www/indexer/vendor', $e[1]['details']);
        $this->assertCount(3, LogReader::parse(self::LOG, 1));
        $this->assertCount(1, LogReader::parse(self::LOG, 4));
        $this->assertCount(1, LogReader::parse(self::LOG, 1, 'Crawler'));
    }
}
