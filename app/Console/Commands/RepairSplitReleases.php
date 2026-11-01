<?php
namespace App\Console\Commands;

use App\Models\Newsgroup;
use App\Models\Release;
use App\Services\ReleaseBuilder;
use App\Services\Search\ReleaseIndex;
use App\Services\SubjectParser;
use Illuminate\Console\Command;

/**
 * Repariert Releases, die vor 1.13.2 pro Datei angelegt wurden (Dateiname ohne Anführungszeichen im Betreff,
 * z.B. "Name - GRP.vol001+002.par2"): führt die Dateien zum richtigen Release zusammen und löscht die Einzelteile.
 */
class RepairSplitReleases extends Command
{
    protected $signature = 'indexer:repair-split {--dry-run : nur zählen}';
    protected $description = 'Pro Datei zerstückelte Releases zusammenführen';

    private const SUFFIXES = ['.par2', '.rar', '.nfo', '.sfv', '.nzb', '.7z', '.zip', '.mkv', '.mp4', '.avi', '.iso'];

    public function handle(ReleaseBuilder $builder): int
    {
        $q = Release::where('locked', false)->where('obfuscated', false)->where(function ($w) {
            foreach (self::SUFFIXES as $s) {
                $w->orWhere('name', 'like', '%' . $s);
            }
            $w->orWhere('name', 'like', '%.r__')->orWhere('name', 'like', '%.0__');
        });
        $total = (clone $q)->count();
        $this->info("$total Releases mit Dateiendung im Namen gefunden.");
        if ($this->option('dry-run') || !$total) {
            return self::SUCCESS;
        }
        $groups = Newsgroup::all()->keyBy('id');
        $removed = 0;
        $q->orderBy('id')->chunkById(200, function ($chunk) use ($builder, $groups, &$removed) {
            $rows = [];
            $old = [];
            foreach ($chunk as $rel) {
                $base = SubjectParser::stripExtensions($rel->name);
                if ($base === '' || $base === $rel->name || !isset($groups[$rel->newsgroup_id]) || !SubjectParser::meaningful(SubjectParser::sceneName($base))) {
                    continue;
                }
                $date = ($rel->posted_at ?? $rel->created_at)->toRfc2822String();
                foreach ((array) $rel->files as $file => $f) {
                    foreach ((array) ($f['segs'] ?? []) as $n => $seg) {
                        // Betreffzeile nachbauen, wie sie heute geparst wird
                        $rows[$rel->newsgroup_id][] = [0, "$file yEnc ($n/" . (int) $f['total'] . ')', $rel->poster, $date, '<' . $seg['id'] . '>', '', (int) $seg['bytes'], 0];
                    }
                }
                // so bildet ReleaseBuilder den Schlüssel: Gruppe | Poster | Name
                $old[$rel->id] = [$rel->guid, sha1($rel->newsgroup_id . '|' . $rel->poster . '|' . mb_strtolower(SubjectParser::sceneName($base)))];
            }
            foreach ($rows as $gid => $r) {
                $builder->ingest($groups[$gid], $r);
            }
            // Einzelteil löschen, sobald das zusammengeführte Release existiert
            $del = [];
            foreach ($old as $id => [$guid, $target]) {
                if ($target !== $guid && Release::where('guid', $target)->exists()) {
                    $del[] = $id;
                }
            }
            if ($del) {
                $removed += Release::whereIn('id', $del)->delete();
                ReleaseIndex::remove($del);
            }
            $this->line("… $removed Einzelteile zusammengeführt");
        });
        $this->info("Fertig: $removed Einzelteile zusammengeführt. Cover und Filminfos folgen beim nächsten Lauf des Zeitplans.");
        return self::SUCCESS;
    }
}
