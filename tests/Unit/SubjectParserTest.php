<?php
namespace Tests\Unit;

use App\Services\SubjectParser;
use PHPUnit\Framework\TestCase;

class SubjectParserTest extends TestCase
{
    public function test_description_style_with_size_and_latin1(): void
    {
        // echte Betreffzeile aus a.b.hou, "ü" als Latin-1-Byte
        $p = SubjectParser::parse("(7/8) - Description - \"Gr\xFC\xDFe aus K\xF6ln 2018.rar.vol12+10.PAR2\" - 318,06 MB - yEnc (4/11)");
        $this->assertSame('Grüße aus Köln 2018.rar.vol12+10.PAR2', $p['file']);
        $this->assertSame(4, $p['part']);
        $this->assertSame(11, $p['parts']);
        $this->assertSame(8, $p['expected']);
        $this->assertSame('', $p['prefixName']);
        $this->assertSame('Grüße aus Köln 2018', $p['fileBase']);
        $this->assertTrue(mb_check_encoding($p['file'], 'UTF-8'));

        $p = SubjectParser::parse("(1/8) - Description - \"Gr\xFC\xDFe aus K\xF6ln 2018.rar\" - 318,06 MB - yEnc (738/827)");
        $this->assertSame('Grüße aus Köln 2018', $p['fileBase']);
        $this->assertSame([738, 827], [$p['part'], $p['parts']]);
    }

    public function test_classic_styles(): void
    {
        $p = SubjectParser::parse('Cool.Show.S01E01.720p [01/20] - "cool.part01.rar" yEnc (1/50)');
        $this->assertSame('Cool.Show.S01E01.720p', $p['prefixName']);
        $this->assertSame(20, $p['expected']);
        $this->assertSame('cool', $p['fileBase']);

        $p = SubjectParser::parse('[01/20] - "Show.Name.S01E02.1080p.WEB-GRP.mkv" (3/80)');
        $this->assertSame('', $p['prefixName']);
        $this->assertSame('Show.Name.S01E02.1080p.WEB-GRP', $p['fileBase']);
        $this->assertSame([3, 80], [$p['part'], $p['parts']]);

        $p = SubjectParser::parse('Movie 2020 - "movie.2020.7z.001" yEnc (2/9)');
        $this->assertSame('movie.2020', $p['fileBase']);
    }

    public function test_no_filename(): void
    {
        $p = SubjectParser::parse('1 yEnc (78/1421)');
        $this->assertSame(['1', 78, 1421, 0, '1', null], [$p['file'], $p['part'], $p['parts'], $p['expected'], $p['prefixName'], $p['fileBase']]);
    }

    public function test_rejects_garbage(): void
    {
        $this->assertNull(SubjectParser::parse('Re: Frage zu meinem Reader'));
        $this->assertNull(SubjectParser::parse('"x.rar" yEnc (5/3)'));
        $this->assertNull(SubjectParser::parse('"x.rar" yEnc (0/3)'));
    }

    public function test_meaningful(): void
    {
        $this->assertTrue(SubjectParser::meaningful('Grüße aus Köln 2018'));
        $this->assertTrue(SubjectParser::meaningful('Linux Live Stick 2018'));
        foreach (['', '1', '12 34', 'Description', 'yEnc', 'ab12cd34ef56ab78cd90ef12', null] as $n) {
            $this->assertFalse(SubjectParser::meaningful($n), var_export($n, true));
        }
    }

    public function test_strip_extensions(): void
    {
        foreach ([
            'Name.rar.vol22+19.PAR2' => 'Name', 'Name.par2' => 'Name', 'Name.part07.rar' => 'Name', 'Name.r12' => 'Name',
            'Name.nfo' => 'Name', 'Show.S01E01.mkv.vol00+01.par2' => 'Show.S01E01', 'Name.7z.003' => 'Name', 'Name 2018' => 'Name 2018',
        ] as $in => $out) {
            $this->assertSame($out, SubjectParser::stripExtensions($in), $in);
        }
    }
}
