<?php
namespace Tests\Feature;

use App\Models\Newsgroup;
use App\Models\Release;
use App\Models\User;
use App\Services\Search\Manticore;
use App\Services\Search\ReleaseIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private RecordingConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();
        config(['indexer.manticore.enabled' => true]);
        $this->conn = new RecordingConnection();
        ReleaseIndex::useClient(new Manticore('releases_idx', 10000, $this->conn));
    }

    protected function tearDown(): void
    {
        ReleaseIndex::useClient(null);
        parent::tearDown();
    }

    private function release(array $o = []): Release
    {
        $g = Newsgroup::firstOrCreate(['name' => 'alt.test']);
        return Release::create(array_merge([
            'guid' => sha1(uniqid('', true)), 'newsgroup_id' => $g->id, 'name' => 'Show.S01E01.1080p', 'poster' => 'p',
            'size' => 5_000_000, 'completion' => 100, 'category_id' => 5040,
            'files' => ['a.rar' => ['total' => 1, 'segs' => [1 => ['id' => 'x@y', 'bytes' => 100]]]], 'posted_at' => now(),
        ], $o));
    }

    private function sql(string $prefix): array
    {
        return array_values(array_filter($this->conn->log, fn ($l) => str_starts_with($l[0], $prefix)));
    }

    public function test_index_follows_visibility(): void
    {
        $r = $this->release(['completion' => 50]);
        $this->assertCount(0, $this->sql('REPLACE'), 'unvollständig: nicht indexieren');
        $this->assertCount(0, $this->sql('DELETE'), 'war nie im Index: nichts löschen');

        $r->update(['completion' => 100]);
        $this->assertCount(1, $this->sql('REPLACE'));

        $r->update(['poster' => 'egal']);
        $this->assertCount(1, $this->sql('REPLACE'), 'irrelevante Änderung: kein neuer Upsert');

        $r->update(['status' => 'spam']);
        $this->assertSame("DELETE FROM releases_idx WHERE id IN ({$r->id})", $this->sql('DELETE')[0][0]);

        $r2 = $this->release();
        $r2->delete();
        $this->assertCount(2, $this->sql('DELETE'));
    }

    public function test_cleanup_removes_from_index(): void
    {
        config(['indexer.retention_days' => 10]);
        $old = $this->release(['posted_at' => now()->subDays(50)]);
        $this->artisan('indexer:cleanup')->assertSuccessful();
        $this->assertStringContainsString("({$old->id})", $this->sql('DELETE')[0][0]);
    }

    public function test_browse_uses_manticore_order_and_total(): void
    {
        $user = User::factory()->create();
        $a = $this->release(['name' => 'Alpha.Show.S01E01', 'posted_at' => now()->subDay()]);
        $b = $this->release(['name' => 'Beta.Show.S01E01', 'posted_at' => now()]);
        $this->conn->results = [[['id' => $a->id], ['id' => $b->id]], [['Variable_name' => 'total_found', 'Value' => '2']]];

        $html = $this->actingAs($user)->get('/browse?q=Show')->assertOk()->getContent();
        $this->assertTrue(strpos($html, 'Alpha.Show') < strpos($html, 'Beta.Show'), 'Reihenfolge von Manticore');
        $this->assertSame(['Show*'], $this->sql('SELECT')[0][1]);
    }

    public function test_falls_back_to_mysql_when_manticore_is_down(): void
    {
        $user = User::factory()->create();
        $this->release(['name' => 'Gamma.Show.S01E01']);
        $this->conn->fail = true;
        $this->actingAs($user)->get('/browse?q=Gamma')->assertOk()->assertSee('Gamma.Show');
        $this->get('/api?t=search&q=Gamma&apikey=' . $user->api_key)->assertOk()->assertSee('Gamma.Show');
    }

    public function test_crawler_keeps_running_when_manticore_is_down(): void
    {
        $this->conn->fail = true;
        $r = $this->release(); // darf keine Exception werfen
        $this->assertNotNull($r->fresh());
    }

    public function test_id_search_skips_manticore(): void
    {
        $user = User::factory()->create();
        $this->get('/api?t=movie&q=Matrix&imdbid=tt0133093&apikey=' . $user->api_key)->assertOk();
        $this->assertCount(0, $this->sql('SELECT'));
    }

    public function test_search_index_command(): void
    {
        $this->release();
        $this->release(['status' => 'spam']);
        $this->conn->log = [];
        $this->artisan('indexer:search-index --fresh')->assertSuccessful();
        $this->assertStringStartsWith('CREATE TABLE IF NOT EXISTS releases_idx', $this->conn->log[0][0]);
        $this->assertSame('TRUNCATE TABLE releases_idx', $this->conn->log[1][0]);
        $this->assertCount(1, $this->sql('REPLACE'));
        $this->assertCount(1, $this->sql('REPLACE')[0][1], 'nur das sichtbare Release');
    }

    public function test_search_index_command_when_disabled(): void
    {
        config(['indexer.manticore.enabled' => false]);
        $this->artisan('indexer:search-index')->assertFailed();
    }
}

class RecordingConnection
{
    public array $log = [];
    public array $results = [];
    public bool $fail = false;

    public function exec(string $sql)
    {
        if ($this->fail) {
            throw new \PDOException('Manticore nicht erreichbar');
        }
        $this->log[] = [$sql, []];
        return 0;
    }

    public function prepare(string $sql)
    {
        if ($this->fail) {
            throw new \PDOException('Manticore nicht erreichbar');
        }
        $conn = $this;
        return new class($conn, $sql) {
            private array $rows = [];
            public function __construct(private $conn, private string $sql) {}
            public function execute(array $params = []): bool
            {
                $this->conn->log[] = [$this->sql, $params];
                $this->rows = array_shift($this->conn->results) ?? [];
                return true;
            }
            public function columnCount(): int { return str_starts_with($this->sql, 'SELECT') || str_starts_with($this->sql, 'SHOW') ? 1 : 0; }
            public function fetchAll($mode = null): array { return $this->rows; }
        };
    }
}
