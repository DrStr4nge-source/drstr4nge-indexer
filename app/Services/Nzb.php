<?php
namespace App\Services;

use App\Models\Download;
use App\Models\Release;
use App\Models\User;

class Nzb
{
    public static function build(Release $r): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
           . '<!DOCTYPE nzb PUBLIC "-//newzBin//DTD NZB 1.1//EN" "http://www.newzbin.com/DTD/nzb/nzb-1.1.dtd">' . "\n"
           . '<nzb xmlns="http://www.newzbin.com/DTD/2003/nzb">' . "\n";

        $group = htmlspecialchars($r->newsgroup->name, ENT_XML1);
        foreach ($r->files as $file => $f) {
            $subject = htmlspecialchars("{$r->name} - \"$file\" yEnc (1/{$f['total']})", ENT_XML1);
            $x .= sprintf(
                '<file poster="%s" date="%d" subject="%s"><groups><group>%s</group></groups><segments>' . "\n",
                htmlspecialchars((string) $r->poster, ENT_XML1), $r->posted_at->timestamp, $subject, $group
            );
            ksort($f['segs']);
            foreach ($f['segs'] as $n => $s) {
                $x .= sprintf('<segment bytes="%d" number="%d">%s</segment>' . "\n",
                    $s['bytes'], $n, htmlspecialchars($s['id'], ENT_XML1));
            }
            $x .= "</segments></file>\n";
        }
        return $x . "</nzb>\n";
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
