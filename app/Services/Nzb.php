<?php
namespace App\Services;

use App\Models\Download;
use App\Models\Release;
use App\Models\User;

class Nzb
{
    public static function build(Release $r): string
    {
        return self::xml((string) $r->name, (string) $r->poster, (int) $r->posted_at?->timestamp, (string) $r->newsgroup?->name, $r->files);
    }

    /** NZB 1.1 aus der Artikel-Liste: [Dateiname => ['total' => n, 'segs' => [nr => ['id' => ..., 'bytes' => ...]]]] */
    public static function xml(string $name, string $poster, int $date, string $group, array $files): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
           . '<!DOCTYPE nzb PUBLIC "-//newzBin//DTD NZB 1.1//EN" "http://www.newzbin.com/DTD/nzb/nzb-1.1.dtd">' . "\n"
           . '<nzb xmlns="http://www.newzbin.com/DTD/2003/nzb">' . "\n";

        $group = self::esc($group);
        foreach ($files as $file => $f) {
            $segs = (array) ($f['segs'] ?? []);
            if (!$segs) {
                continue;
            }
            $total = (int) ($f['total'] ?? 0) ?: count($segs);
            $x .= sprintf(
                '<file poster="%s" date="%d" subject="%s"><groups><group>%s</group></groups><segments>' . "\n",
                self::esc($poster), $date, self::esc("$name - \"$file\" yEnc (1/$total)"), $group
            );
            ksort($segs);
            foreach ($segs as $n => $s) {
                $x .= sprintf('<segment bytes="%d" number="%d">%s</segment>' . "\n",
                    (int) ($s['bytes'] ?? 0), (int) $n, self::esc(trim((string) ($s['id'] ?? ''), '<> ')));
            }
            $x .= "</segments></file>\n";
        }
        return $x . "</nzb>\n";
    }

    /** Sicher für XML-Attribute und -Text: Anführungszeichen, &, <, >, kaputtes UTF-8 und Steuerzeichen */
    private static function esc(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Prüft Limits, zählt den Download und liefert die NZB-Datei. */
    public static function download(User $u, Release $r)
    {
        abort_if($u->is_banned, 403, 'Account gesperrt.');
        abort_unless($u->canGrab(), 429, 'Tageslimit erreicht. VIP hat kein Limit.');

        Download::create(['user_id' => $u->id, 'release_id' => $r->id]);

        $fname = preg_replace('/[^\w.\- ]+/', '_', $r->name) . '.nzb';
        return response(self::build($r), 200, [
            'Content-Type' => 'application/x-nzb',
            'Content-Disposition' => 'attachment; filename="' . $fname . '"',
        ]);
    }
}
