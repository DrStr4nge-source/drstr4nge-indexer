<?php
namespace Tests\Unit;

use App\Services\Nzb;
use PHPUnit\Framework\TestCase;

class NzbTest extends TestCase
{
    private function parse(string $xml): \SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'NZB ist kein gültiges XML: ' . implode(' | ', array_map(fn ($e) => trim($e->message), libxml_get_errors())));
        libxml_clear_errors();
        return $doc;
    }

    public function test_quotes_and_special_chars_give_valid_xml(): void
    {
        $files = [
            'Film.2026 - "TMSF".vol063+064.par2' => ['total' => 2, 'segs' => [2 => ['id' => 'b@x', 'bytes' => 5], 1 => ['id' => 'a&b@x', 'bytes' => 7]]],
            "Kaputt\x01\xff.rar" => ['total' => 1, 'segs' => [1 => ['id' => 'c@x', 'bytes' => 9]]],
            'leer.nfo' => ['total' => 1, 'segs' => []],
        ];
        $doc = $this->parse(Nzb::xml('Film "Teil 1" & <Mehr>', 'Ich <ich@nomail.invalid>', 1791659578, 'alt.binaries.test', $files));
        $doc->registerXPathNamespace('n', 'http://www.newzbin.com/DTD/2003/nzb');
        $this->assertCount(2, $doc->xpath('//n:file'));
        $first = $doc->xpath('//n:file')[0];
        $this->assertStringContainsString('"TMSF"', (string) $first['subject']);
        $this->assertSame('Ich <ich@nomail.invalid>', (string) $first['poster']);
        $segs = $doc->xpath('//n:file[1]//n:segment');
        $this->assertSame('a&b@x', (string) $segs[0]);
        $this->assertSame('1', (string) $segs[0]['number']);
    }
}
