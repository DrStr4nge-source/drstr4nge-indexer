<?php
namespace App\Services;

use App\Models\Blacklist;
use App\Models\Newsgroup;
use App\Models\Release;
use Carbon\Carbon;

/** Macht aus XOVER-Zeilen Releases, erkennt Kategorie, Spam, Passwort-Hinweise und verschleierte Namen. */
class ReleaseBuilder
{
    public function ingest(Newsgroup $group, array $rows): int
    {
        Blacklist::flush();
        $acc = [];
        foreach ($rows as $r) {
            if (count($r) < 7 || !($p = SubjectParser::parse((string) $r[1]))) {
                continue;
            }
            $poster = SubjectParser::utf8((string) $r[2]);
            $posted = strtotime((string) $r[3]) ?: time();
            $expected = $p['expected'];

            if (SubjectParser::meaningful($p['prefixName'])) {
                // Name steht im Betreff ("Name [01/20] - "datei.rar" ...")
                $name = $p['prefixName'];
                $guid = sha1($group->id . '|' . $poster . '|' . mb_strtolower($name));
                $anon = false;
            } elseif (SubjectParser::meaningful($p['fileBase'])) {
                // Betreff hat nur Platzhalter ("Description"): Name aus dem Dateinamen
                $name = $p['fileBase'];
                $guid = sha1($group->id . '|' . $poster . '|f|' . mb_strtolower($name));
                $anon = false;
                $expected = 0; // (x/y) zählt hier alle Dateien des Posts, nicht nur dieses Releases
            } elseif ($expected > 0) {
                // Verschleierter Post: alle Dateien eines Releases teilen Poster, Dateianzahl und Stunde
                $guid = sha1($group->id . '|' . $poster . '|obf|' . $expected . '|' . gmdate('YmdH', $posted));
                $name = $p['fileBase'] ?: $p['file'];
                $anon = true;
            } else {
                $guid = sha1($group->id . '|' . $poster . '|file|' . $p['file'] . '|' . $p['parts']);
                $name = $p['fileBase'] ?: $p['file'];
                $anon = true;
            }

            $file = mb_substr($p['file'], 0, 255);
            $acc[$guid]['name'] ??= mb_substr($name, 0, 500);
            $acc[$guid]['anon'] ??= $anon;
            $acc[$guid]['poster'] ??= mb_substr($poster, 0, 255);
            $acc[$guid]['expected'] = max($acc[$guid]['expected'] ?? 0, $expected);
            $acc[$guid]['posted'] = max($acc[$guid]['posted'] ?? 0, $posted);
            $acc[$guid]['files'][$file]['total'] = $p['parts'];
            $acc[$guid]['files'][$file]['segs'][$p['part']] = [
                'id' => trim((string) $r[4], '<>'),
                'bytes' => (int) $r[6],
            ];
        }

        foreach ($acc as $guid => $d) {
            $rel = Release::firstOrNew(['guid' => $guid]);
            $isNew = !$rel->exists;
            $files = $isNew ? [] : $rel->files;
            foreach ($d['files'] as $file => $f) {
                $files[$file]['total'] = $f['total'];
                foreach ($f['segs'] as $n => $seg) {
                    $files[$file]['segs'][$n] = $seg;
                }
            }

            $have = $total = $size = 0;
            foreach ($files as $f) {
                $have += count($f['segs']);
                $total += $f['total'];
                $size += array_sum(array_column($f['segs'], 'bytes'));
            }
            $expected = max((int) $rel->files_expected, $d['expected']);
            $seen = count($files);
            if ($expected > $seen && $seen > 0) { // komplett fehlende Dateien einrechnen
                $total += (int) (($expected - $seen) * ceil($total / $seen));
            }

            if ($isNew) {
                $rel->name = $d['name'];
            }
            $rel->fill([
                'newsgroup_id' => $group->id,
                'poster' => $d['poster'],
                'size' => $size,
                'completion' => $total ? min(100, (int) floor($have / $total * 100)) : 0,
                'files' => $files,
                'files_expected' => $expected ?: null,
                'posted_at' => Carbon::createFromTimestamp($d['posted']),
            ]);

            if (!$rel->inspected) {
                $rel->obfuscated = $d['anon'] || Obfuscation::looks($rel->name);
            }
            $this->classify($rel);
            $rel->save();
        }
        return count($acc);
    }

    public function classify(Release $rel): void
    {
        $names = array_keys($rel->files);
        $rel->category_id = Categorizer::detect($rel->name, $names);
        if ($rel->locked || $rel->completion < config('indexer.min_completion')) {
            return; // unvollständige Releases sind eh unsichtbar; Admin-Entscheidung bleibt
        }
        $new = Classifier::status(
            $rel->name,
            $names,
            (int) $rel->size,
            Blacklist::matches($rel->poster, $rel->obfuscated ? null : $rel->name),
            (int) config('indexer.min_size'),
            (array) config('indexer.spam_words')
        );
        // Ein bei der Prüfung gefundenes "passworded" nicht durch spätere Batches zurücksetzen
        $rel->status = ($new === 'ok' && $rel->inspected) ? $rel->status : $new;
    }
}
