<?php

namespace Tests\Unit;

use App\Services\Health;
use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private array $cfg = ['crawl_hours' => 6, 'irc_minutes' => 30, 'disk_percent' => 90];

    private function m(array $o = []): array
    {
        $now = 1_800_000_000;
        return $o + ['now' => $now, 'first_seen' => $now - 30 * 86400, 'active_groups' => 2, 'nntp_configured' => true,
            'release_updated_at' => $now - 600, 'irc_enabled' => true, 'irc_down_since' => null, 'irc_error' => null,
            'disk_percent' => 40.0, 'disk_free' => '50 GB', 'backup_enabled' => true, 'backup_at' => $now - 3600, 'backup_error' => null];
    }

    public function test_alles_gut(): void
    {
        $this->assertSame([], Health::problems($this->m(), $this->cfg));
    }

    public function test_crawler_steht(): void
    {
        $p = Health::problems($this->m(['release_updated_at' => 1_800_000_000 - 7 * 3600]), $this->cfg);
        $this->assertSame(['crawl'], array_keys($p));
        $this->assertStringContainsString('seit 7 Stunden', $p['crawl']);
        // ohne Gruppen oder ohne Usenet-Zugang keine Warnung
        $this->assertSame([], Health::problems($this->m(['release_updated_at' => 0, 'active_groups' => 0]), $this->cfg));
    }

    public function test_irc_platte_sicherung(): void
    {
        $now = 1_800_000_000;
        $p = Health::problems($this->m(['irc_down_since' => $now - 45 * 60, 'irc_error' => 'Banned', 'disk_percent' => 93.5, 'backup_at' => $now - 30 * 3600]), $this->cfg);
        $this->assertSame(['irc', 'disk', 'backup'], array_keys($p));
        $this->assertStringContainsString('seit 45 Minuten', $p['irc']);
        $this->assertStringContainsString('Banned', $p['irc']);
        $this->assertStringContainsString('93,5 %', $p['disk']);
        $this->assertStringContainsString('30 Stunden', $p['backup']);
        // IRC erst nach 30 Minuten, frische Installation ohne Sicherung erst nach 26 Std
        $this->assertSame([], Health::problems($this->m(['irc_down_since' => $now - 600, 'backup_at' => null, 'first_seen' => $now - 3600]), $this->cfg));
        $this->assertSame(['backup'], array_keys(Health::problems($this->m(['backup_error' => 'gdrive: token expired']), $this->cfg)));
    }
}
