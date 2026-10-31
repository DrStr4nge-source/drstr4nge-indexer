<?php

namespace Tests\Unit;

use App\Models\Predb;
use App\Services\Pre\IrcClient;
use App\Services\Pre\IrcSettings;
use App\Services\Pre\PredbNet;
use App\Services\Pre\PreParser;
use App\Services\Pre\PreRss;
use App\Services\Pre\PreStore;
use PHPUnit\Framework\TestCase;

class PreTest extends TestCase
{
    public function test_neue_ansage_mit_farben(): void
    {
        $line = "\x0303NEW:\x03 [DT: 2026-10-31 14:00:05] [TT: \x02Some.Movie.2026.German.1080p.WEB.h264-GRP\x02] [SC: srrdb] [CT: X264] [RQ: N/A] [SZ: 4.37GB] [FL: 47] [FN: N/A]";
        $p = PreParser::parse($line);
        $this->assertSame('NEW', $p['type']);
        $this->assertSame('Some.Movie.2026.German.1080p.WEB.h264-GRP', $p['title']);
        $this->assertSame('X264', $p['category']);
        $this->assertSame('srrdb', $p['source']);
        $this->assertSame((int) round(4.37 * 1024 ** 3), $p['size']);
        $this->assertSame(47, $p['files']);
        $this->assertNull($p['filename']);
        $this->assertSame('2026-10-31 14:00:05', $p['predate']);
        $this->assertNull($p['nuked']);
    }

    public function test_nuke_und_unnuke(): void
    {
        $p = PreParser::parse('NUK: [DT: 2026-10-31 15:00:00] [TT: Show.S01E01.720p.HDTV.x264-ABC] [SC: pre] [CT: TV] [RQ: N/A] [SZ: N/A] [FL: N/A] [NUKED: dupe.S01E01.2026-10-30]');
        $this->assertSame(Predb::NUKED, $p['nuked']);
        $this->assertSame('dupe.S01E01.2026-10-30', $p['nuke_reason']);
        $this->assertNull($p['size']);
        $this->assertNull($p['files']);
        $u = PreParser::parse('NUK: [DT: 2026-10-31 15:00:00] [TT: Show.S01E01.720p.HDTV.x264-ABC] [SC: pre] [CT: TV] [RQ: N/A] [SZ: N/A] [FL: N/A] [UNNUKED: fixed]');
        $this->assertSame(Predb::UNNUKED, $u['nuked']);
        $m = PreParser::parse('NUK: [DT: 2026-10-31 15:00:00] [TT: A.B-C] [SC: x] [CT: TV] [RQ: N/A] [SZ: N/A] [FL: N/A] [MODNUKE: bad.ar]');
        $this->assertSame(Predb::MODNUKED, $m['nuked']);
    }

    public function test_keine_ansage(): void
    {
        $this->assertNull(PreParser::parse('Hallo zusammen'));
        $this->assertNull(PreParser::parse('NEW: [DT: 2026-10-31 14:00:05] [TT: Name mit Leerzeichen] [SC: x] [CT: X] [RQ: N/A] [SZ: 1MB] [FL: 1]'));
    }

    public function test_groessen(): void
    {
        $this->assertSame(1024 * 1024, PreParser::bytes('1MB'));
        $this->assertSame(512 * 1024, PreParser::bytes('512KB'));
        $this->assertSame((int) round(1.5 * 1024 ** 4), PreParser::bytes('1,5 TB'));
        $this->assertNull(PreParser::bytes('irgendwas'));
    }

    public function test_zusammenfuehren(): void
    {
        $old = ['category' => 'X264', 'source' => 'srrdb', 'size' => 100, 'files' => 5, 'filename' => null, 'predate' => '2026-10-31 14:00:00', 'nuked' => 0, 'nuke_reason' => null];
        // UPD ohne Größe, mit späterer Zeit: nichts überschreiben
        $this->assertSame([], PreStore::merge($old, ['category' => 'X264', 'source' => null, 'size' => null, 'files' => null, 'predate' => '2026-10-31 15:00:00', 'nuked' => null]));
        // Nuke kommt dazu
        $this->assertSame(['nuked' => Predb::NUKED, 'nuke_reason' => 'dupe'], PreStore::merge($old, ['size' => 100, 'nuked' => Predb::NUKED, 'nuke_reason' => 'dupe']));
        // neuer Eintrag
        $new = PreStore::merge(null, ['category' => 'TV', 'size' => 7, 'predate' => '2026-10-31 14:00:00', 'nuked' => null]);
        $this->assertSame(['category' => 'TV', 'size' => 7, 'predate' => '2026-10-31 14:00:00'], $new);
    }

    public function test_predb_net_antwort(): void
    {
        $json = ['status' => 'success', 'data' => [
            ['id' => 1, 'pretime' => 1793455200, 'release' => 'Some.Album-WEB-2026-GRP', 'section' => 'MP3-WEB', 'files' => 12, 'size' => 95.5, 'status' => 0, 'reason' => '', 'group' => 'GRP'],
            ['id' => 2, 'pretime' => 1793455300, 'release' => 'Show.S01E01.1080p.WEB.h264-ABC', 'section' => 'TV-HD', 'files' => 0, 'size' => 0, 'status' => 1, 'reason' => 'dupe'],
            ['id' => 3, 'release' => 'kaputt mit leerzeichen'],
        ]];
        $rows = PredbNet::rows($json);
        $this->assertCount(2, $rows);
        $this->assertSame('MP3-WEB', $rows[0]['category']);
        $this->assertSame((int) round(95.5 * 1024 * 1024), $rows[0]['size']);
        $this->assertSame(gmdate('Y-m-d H:i:s', 1793455200), $rows[0]['predate']);
        $this->assertNull($rows[0]['nuked']);
        $this->assertNull($rows[1]['size']);
        $this->assertNull($rows[1]['files']);
        $this->assertSame(Predb::NUKED, $rows[1]['nuked']);
        $this->assertSame('dupe', $rows[1]['nuke_reason']);
        $this->assertCount(1, PredbNet::rows([['release' => 'A.B-C', 'pretime' => 1]])); // ohne Hülle
    }

    public function test_irc_zeilen(): void
    {
        $m = IrcClient::parse(':PreBot!bot@host.example PRIVMSG #PreNNTmux :NEW: [DT: x]');
        $this->assertSame('PreBot', $m['nick']);
        $this->assertSame('PRIVMSG', $m['command']);
        $this->assertSame(['#PreNNTmux'], $m['params']);
        $this->assertSame('NEW: [DT: x]', $m['trailing']);
        $n = IrcClient::parse(':irc.example 433 * MeinNick :Nickname is already in use');
        $this->assertSame('433', $n['command']);
        $p = IrcClient::parse('PING :irc.example');
        $this->assertSame('PING', $p['command']);
        $this->assertSame('', $p['nick']);
    }

    public function test_rss_feed(): void
    {
        $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>PREdb</title>'
            . '<item><title>Some.Movie.2026.German.1080p.WEB.h264-GRP</title><pubDate>Sat, 31 Oct 2026 14:00:05 +0100</pubDate><category>Movies-HD</category></item>'
            . '<item><title><![CDATA[Show.S01E01.720p.HDTV.x264-A&amp;B]]></title><pubDate>Sat, 31 Oct 2026 13:00:00 GMT</pubDate></item>'
            . '<item><title>kein release</title></item></channel></rss>';
        $rows = PreRss::parse($xml, 'predb.me');
        $this->assertCount(2, $rows);
        $this->assertSame('2026-10-31 13:00:05', $rows[0]['predate']);
        $this->assertSame('Movies-HD', $rows[0]['category']);
        $this->assertSame('predb.me', $rows[0]['source']);
        $this->assertSame('Show.S01E01.720p.HDTV.x264-A&B', $rows[1]['title']);
    }

    public function test_kanaele_aus_admin_eingabe(): void
    {
        $this->assertSame(['#PreNNTmux', '#nZEDbPRE', '#neu'], IrcSettings::channels("#PreNNTmux, nZEDbPRE\n#neu\n#prennTmux"));
        $this->assertSame([], IrcSettings::channels(' , '));
    }
}
