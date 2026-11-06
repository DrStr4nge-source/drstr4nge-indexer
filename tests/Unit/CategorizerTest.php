<?php
namespace Tests\Unit;

use App\Services\Categorizer;
use PHPUnit\Framework\TestCase;

class CategorizerTest extends TestCase
{
    public function test_names(): void
    {
        $cases = [
            ['Show.Name.S01E02.1080p.WEB.h264-GRP', [], 5040],
            ['Show.Name.S01.2160p.WEB-DL', [], 5045],
            ['Show.Name.S01E01.720p.HDTV.x264', [], 5040],
            ['Show.S02E03.DVDRip.XviD', [], 5030],
            ['Show_Name_S03E04_480p', [], 5030],
            ['Daily.Show.2024.05.12.720p.WEB', [], 5040],
            ['UFC.300.S01E01.1080p', [], 5060],
            ['[SubsPlease] Some Anime - 01 (1080p)', [], 5070],
            ['Movie.Name.2021.1080p.BluRay.x264-GRP', [], 2040],
            ['Movie.Name.2021.2160p.UHD.BluRay.REMUX', [], 2045],
            ['Movie.Name.2019.BDRip.XviD', [], 2030],
            ['Movie.2019.COMPLETE.BLURAY-GRP', [], 2050],
            ['Some.Game-CODEX', [], 4050],
            ['Some.Game.PS4-DUPLEX', [], 1180],
            ['Photo.Tool.Pro.v2.Portable.x64', [], 4010],
            ['Linux.Distro.1.0.ISO', [], 4020],
            ['Name.XXX.1080p', [], 6000],
            ['randomname', [], 7000],
            // Anime-Film / Anime-Serie / Doku
            ['[SubsPlease] Chainsaw Man The Movie Reze Arc (2025) (1080p)', [], 2100],
            ['[PHTM] Josee, the Tiger and the Fish (2020) (BD 1080p x265 Opus 5.1) (Multi-Audio) (German_Deutsch)', [], 2100],
            ['[Fuechslein] KILL la KILL - S01 (BD 1080p AV1 Opus 2.0) [Dual-Audio] [Multi-Audio] (Japanese, German_Deutsch)', [], 5070],
            ['DemiHuman KonoSuba BD 1080p HEVC Opus 2.0 Dual - Audio, Multi - Audio, Multi - Sub - Japanese, English, German Dubs', [], 5070],
            ['[Group] Some Show - 12 (1080p) [Dual-Audio]', [], 5070],
            ['Terra.X.Faszination.Erde.S05E02.Doku.German.1080p.WEB.h264-GRP', [], 5080],
            ['Die.Nordsee.Dokumentation.2024.German.1080p.WEB.x264-GRP', [], 5080],
            ['Street.Fighter.2026.German.CAM.1080p.x264-LiNEUP', [], 2040],
            ['Josie.der.Tiger.und.die.Fische.2020.GERMAN.ML.1080p.BD.Opus.5.1.10bit.x265-PHTM', [], 2040],
            ['Tatort.S55E10.German.720p.WEB.h264-GRP', [], 5040],
        ];
        foreach ($cases as [$name, $files, $expected]) {
            $this->assertSame($expected, Categorizer::detect($name, $files), $name);
        }
    }

    public function test_extensions(): void
    {
        $this->assertSame(3040, Categorizer::detect('Artist-Album-2020-FLAC', ['01.flac', 'a.nfo']));
        $this->assertSame(3010, Categorizer::detect('Artist-Album', ['a.mp3']));
        $this->assertSame(3030, Categorizer::detect('Some.Audiobook', ['a.m4b']));
        $this->assertSame(7020, Categorizer::detect('Some Book', ['book.epub']));
        $this->assertSame(7030, Categorizer::detect('Some Comic', ['c.cbr']));
    }
}
