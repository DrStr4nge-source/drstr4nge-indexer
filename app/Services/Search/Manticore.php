<?php
namespace App\Services\Search;

/**
 * Schlanker Manticore-Client über das MySQL-Protokoll (Port 9306), ohne Zusatzpaket.
 * Die Verbindung ist austauschbar (für Tests); im Betrieb wird sie per PDO aufgebaut.
 */
class Manticore
{
    /** Sonderzeichen der Manticore-Abfragesprache, die maskiert werden müssen. */
    private const SPECIAL = ['\\', '!', '"', '$', "'", '(', ')', '-', '/', '<', '@', '^', '|', '~', '&', '=', '*', '?', '%'];

    /** @var \PDO|object|null */
    private $pdo;

    public function __construct(
        private string $table = 'releases_idx',
        private int $maxResults = 10000,
        $pdo = null,
        private array $dsn = [],
    ) {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $this->table)) {
            throw new \InvalidArgumentException('Ungültiger Tabellenname für Manticore.');
        }
        $this->pdo = $pdo;
    }

    public static function fromConfig(): self
    {
        $c = config('indexer.manticore');
        return new self((string) $c['table'], (int) $c['max_results'], null, ['host' => $c['host'], 'port' => (int) $c['port']]);
    }

    /** Einzelnes Wort maskieren (ein Backslash; PDO verdoppelt ihn beim Quoten, wie Manticore es verlangt). */
    public static function escape(string $term): string
    {
        $out = '';
        foreach (mb_str_split($term) as $ch) {
            $out .= in_array($ch, self::SPECIAL, true) ? '\\' . $ch : $ch;
        }
        return $out;
    }

    /**
     * Macht aus einer Nutzereingabe eine sichere Manticore-Abfrage: alle Wörter müssen vorkommen,
     * Wörter ab 2 Zeichen auch als Wortanfang (Matr findet Matrix).
     */
    public static function matchExpression(?string $q): ?string
    {
        // Wie Manticore standardmäßig indexiert: nur Buchstaben und Ziffern bilden Wörter, alles andere trennt
        $words = preg_split('/[^\p{L}\p{N}]+/u', (string) $q, -1, PREG_SPLIT_NO_EMPTY);
        $parts = [];
        foreach (array_slice($words, 0, 16) as $w) {
            $w = self::escape(mb_substr($w, 0, 64)); // Sicherheitsnetz, sollte hier nichts mehr finden
            $parts[] = mb_strlen($w) >= 2 ? $w . '*' : $w;
        }
        return $parts ? implode(' ', $parts) : null;
    }

    public function createTable(): void
    {
        $this->exec("CREATE TABLE IF NOT EXISTS {$this->table}(name text, category_id int, posted_at timestamp, size bigint) min_prefix_len='2'");
    }

    public function truncate(): void
    {
        $this->exec("TRUNCATE TABLE {$this->table}");
    }

    /** @param array<int,array{id:int,name:string,category_id:int,posted_at:int,size:int}> $rows */
    public function upsert(array $rows): void
    {
        if (!$rows) {
            return;
        }
        $values = [];
        $params = [];
        foreach ($rows as $r) {
            $values[] = sprintf('(%d, ?, %d, %d, %d)', $r['id'], $r['category_id'], max(0, $r['posted_at']), max(0, $r['size']));
            $params[] = $r['name'];
        }
        $this->run("REPLACE INTO {$this->table}(id, name, category_id, posted_at, size) VALUES " . implode(', ', $values), $params);
    }

    /** @param int[] $ids */
    public function delete(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach (array_chunk($ids, 1000) as $chunk) {
            $this->exec("DELETE FROM {$this->table} WHERE id IN (" . implode(',', $chunk) . ')');
        }
    }

    /**
     * @param int[] $categories
     * @return array{ids:int[],total:int}|null null = Abfrage leer (dann normale Suche nutzen)
     */
    public function search(?string $q, array $categories = [], ?int $postedAfter = null, int $offset = 0, int $limit = 50): ?array
    {
        $match = self::matchExpression($q);
        if ($match === null) {
            return null;
        }
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        if ($offset >= $this->maxResults) {
            return ['ids' => [], 'total' => $this->maxResults];
        }
        $limit = min($limit, $this->maxResults - $offset);

        $sql = "SELECT id FROM {$this->table} WHERE MATCH(?)";
        if ($categories) {
            $sql .= ' AND category_id IN (' . implode(',', array_map('intval', $categories)) . ')';
        }
        if ($postedAfter !== null) {
            $sql .= ' AND posted_at >= ' . max(0, $postedAfter);
        }
        $maxMatches = max(1000, $offset + $limit);
        $sql .= " ORDER BY posted_at DESC, id DESC LIMIT $offset, $limit OPTION max_matches=$maxMatches";

        $rows = $this->run($sql, [$match]);
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);

        $total = count($ids) + $offset;
        foreach ($this->run('SHOW META') as $m) {
            if (($m['Variable_name'] ?? null) === 'total_found') {
                $total = (int) $m['Value'];
            }
        }
        return ['ids' => $ids, 'total' => min($total, $this->maxResults)];
    }

    private function connection()
    {
        if ($this->pdo === null) {
            $this->pdo = new \PDO(
                sprintf('mysql:host=%s;port=%d', $this->dsn['host'] ?? '127.0.0.1', $this->dsn['port'] ?? 9306),
                null,
                null,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_EMULATE_PREPARES => true, // Manticore: Werte clientseitig einsetzen
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_TIMEOUT => 3,
                ]
            );
        }
        return $this->pdo;
    }

    private function exec(string $sql): void
    {
        $this->connection()->exec($sql);
    }

    private function run(string $sql, array $params = []): array
    {
        $st = $this->connection()->prepare($sql);
        $st->execute($params);
        return $st->columnCount() > 0 ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
    }
}
