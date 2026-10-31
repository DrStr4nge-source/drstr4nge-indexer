<?php
namespace Tests\Unit;

use App\Services\TitleParser;
use PHPUnit\Framework\TestCase;

class TitleParserTest extends TestCase
{
    public function test_tv(): void
    {
        $this->assertSame(['type' => 'tv', 'title' => 'Show Name', 'year' => null, 'season' => 1, 'episode' => 2],
            TitleParser::parse('Show.Name.S01E02.1080p.WEB.h264-GRP'));
        $this->assertSame(['type' => 'tv', 'title' => 'Show Name', 'year' => 2019, 'season' => 2, 'episode' => 10],
            TitleParser::parse('Show.Name.2019.S02E10.720p'));
        $this->assertSame(['type' => 'tv', 'title' => 'Some Show', 'year' => null, 'season' => 3, 'episode' => null],
            TitleParser::parse('Some.Show.S03.1080p.WEB-DL'));
        $this->assertSame(['type' => 'tv', 'title' => 'Other Show', 'year' => null, 'season' => 4, 'episode' => 12],
            TitleParser::parse('Other_Show_4x12_720p'));
    }

    public function test_daily_and_anime(): void
    {
        $r = TitleParser::parse('The.Daily.Show.2024.05.12.720p');
        $this->assertSame('tv', $r['type']);
        $this->assertSame('The Daily Show', $r['title']);
        $r = TitleParser::parse('[SubsPlease] Some Anime - 05 (1080p)');
        $this->assertSame(['type' => 'tv', 'title' => 'Some Anime', 'year' => null, 'season' => null, 'episode' => 5], $r);
    }

    public function test_movies(): void
    {
        $this->assertSame(['type' => 'movie', 'title' => 'Movie Name', 'year' => 2021, 'season' => null, 'episode' => null],
            TitleParser::parse('Movie.Name.2021.1080p.BluRay.x264-GRP'));
        $this->assertSame(2019, TitleParser::parse('1917.2019.1080p.BluRay')['year']);
        $this->assertSame('1917', TitleParser::parse('1917.2019.1080p.BluRay')['title']);
        $this->assertSame('2001 A Space Odyssey', TitleParser::parse('2001.A.Space.Odyssey.1968.1080p.BluRay')['title']);
        $r = TitleParser::parse('Movie.Name.German.DL.2020.1080p.BluRay');
        $this->assertSame('Movie Name', $r['title']);
        $this->assertSame(2020, $r['year']);
    }

    public function test_unparseable(): void
    {
        $this->assertNull(TitleParser::parse('randomname'));
        $this->assertNull(TitleParser::parse('Movie.Without.Year.1080p'));
        $this->assertNull(TitleParser::parse('S01E01.720p'));
    }

    public function test_similarity(): void
    {
        $this->assertTrue(TitleParser::similarity('The Matrix', 'the.matrix') > 95);
        $this->assertTrue(TitleParser::similarity('Amélie', 'Amelie') > 90);
        $this->assertTrue(TitleParser::similarity('The Matrix', 'Completely Different') < 50);
        $this->assertSame(0.0, TitleParser::similarity('', 'x'));
    }
}
