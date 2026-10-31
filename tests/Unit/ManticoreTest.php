<?php
namespace Tests\Unit;

use App\Services\Search\Manticore;
use PHPUnit\Framework\TestCase;

/** Nachgestellte Verbindung: schreibt alle SQL-Befehle mit und liefert vorgegebene Ergebnisse. */
class FakeManticoreConnection
{
    public array $log = [];
    public array $results = [];

    public function exec(string $sql)
    {
        $this->log[] = [$sql, []];
        return 0;
    }

    public function prepare(string $sql)
    {
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

class ManticoreTest extends TestCase
{
    public function test_escape_special_characters(): void
    {
        $this->assertSame('r\\&b \\(live\\)', Manticore::escape('r&b (live)'));
        $this->assertSame('\\\\', Manticore::escape('\\'));
        $this->assertSame("l\\'italiano", Manticore::escape("l'italiano"));
        $this->assertSame('\\$\\@\\^\\|\\~\\!\\"\\/\\<\\=\\*\\?\\%', Manticore::escape('$@^|~!"/<=*?%'));
        $this->assertSame('Ärger', Manticore::escape('Ärger'));
    }

    public function test_match_expression(): void
    {
        $this->assertSame('The* Matrix* 1999*', Manticore::matchExpression('The.Matrix.1999'));
        $this->assertSame('WEB* DL* x', Manticore::matchExpression('WEB-DL x'));
        $this->assertSame('foo* bar*', Manticore::matchExpression('  foo)   bar_ '));
        $this->assertSame('r b live*', Manticore::matchExpression('r&b (live)'));
        $this->assertSame('Ärger* über* 2024*', Manticore::matchExpression('Ärger über 2024'));
        $this->assertNull(Manticore::matchExpression('   '));
        $this->assertNull(Manticore::matchExpression(null));
        $this->assertNull(Manticore::matchExpression('---'));
        $many = Manticore::matchExpression(implode(' ', array_fill(0, 40, 'ab')));
        $this->assertSame(16, count(explode(' ', $many)));
    }

    public function test_search_builds_safe_query_and_reads_total(): void
    {
        $c = new FakeManticoreConnection();
        $c->results = [[['id' => '7'], ['id' => '3']], [['Variable_name' => 'total', 'Value' => '2'], ['Variable_name' => 'total_found', 'Value' => '42']]];
        $m = new Manticore('releases_idx', 10000, $c);

        $res = $m->search("Matrix'; DROP", [5040, '2040abc'], 1700000000, 50, 25);
        $this->assertSame(['ids' => [7, 3], 'total' => 42], $res);

        [$sql, $params] = $c->log[0];
        $this->assertSame('SELECT id FROM releases_idx WHERE MATCH(?) AND category_id IN (5040,2040) AND posted_at >= 1700000000 ORDER BY posted_at DESC, id DESC LIMIT 50, 25 OPTION max_matches=1000', $sql);
        $this->assertSame(['Matrix* DROP*'], $params); // Nutzereingabe nur als Parameter, nie im SQL-Text
        $this->assertSame('SHOW META', $c->log[1][0]);
    }

    public function test_search_caps_deep_paging(): void
    {
        $c = new FakeManticoreConnection();
        $c->results = [[['id' => 1]], [['Variable_name' => 'total_found', 'Value' => '999999']]];
        $m = new Manticore('releases_idx', 1500, $c);

        $res = $m->search('x y', [], null, 1490, 100);
        $this->assertSame(1500, $res['total']);
        $this->assertStringContainsString('LIMIT 1490, 10 OPTION max_matches=1500', $c->log[0][0]);

        $this->assertSame(['ids' => [], 'total' => 1500], $m->search('x', [], null, 1500, 50));
        $this->assertCount(2, $c->log); // keine weitere Abfrage
        $this->assertNull($m->search('  ', [], null, 0, 50));
    }

    public function test_upsert_delete_create(): void
    {
        $c = new FakeManticoreConnection();
        $m = new Manticore('releases_idx', 10000, $c);
        $m->upsert([
            ['id' => 1, 'name' => "It's.A.Show.S01E01", 'category_id' => 5040, 'posted_at' => 1700000000, 'size' => 123],
            ['id' => 2, 'name' => 'Other', 'category_id' => 2040, 'posted_at' => -5, 'size' => 9],
        ]);
        $this->assertSame('REPLACE INTO releases_idx(id, name, category_id, posted_at, size) VALUES (1, ?, 5040, 1700000000, 123), (2, ?, 2040, 0, 9)', $c->log[0][0]);
        $this->assertSame(["It's.A.Show.S01E01", 'Other'], $c->log[0][1]);

        $m->upsert([]);
        $this->assertCount(1, $c->log);

        $m->delete([3, '4', 3]);
        $this->assertSame('DELETE FROM releases_idx WHERE id IN (3,4)', $c->log[1][0]);
        $m->delete(range(1, 2500));
        $this->assertCount(5, $c->log); // 3 Blöcke à max. 1000

        $m->createTable();
        $this->assertSame("CREATE TABLE IF NOT EXISTS releases_idx(name text, category_id int, posted_at timestamp, size bigint) min_prefix_len='2'", end($c->log)[0]);
    }

    public function test_rejects_bad_table_name(): void
    {
        try {
            new Manticore('x; DROP TABLE y');
            $this->assertTrue(false, 'keine Exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }
}
