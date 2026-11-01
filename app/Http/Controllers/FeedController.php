<?php
namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\User;
use App\Models\Watch;
use App\Services\Categories;
use Illuminate\Http\Request;

/**
 * RSS-Feed pro User (für NZBGet, SABnzbd, Sonarr-RSS usw.):
 *   /rss?apikey=KEY                 neueste Releases
 *   /rss?apikey=KEY&cat=2000,5000   nur diese Kategorien
 *   /rss?apikey=KEY&t=merkliste     neue Releases zu Titeln auf der Merkliste
 * Die NZB-Links enthalten den API-Key, Downloads zählen wie über die API.
 */
class FeedController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = User::where('api_key', (string) $request->query('apikey'))->first();
        if (!$user || $user->is_banned) {
            return response('Falscher oder fehlender API-Key', 401)->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        $watch = $request->query('t') === 'merkliste';
        $q = Release::lite()->visible()->with('media')->orderByDesc('posted_at')->limit(min(100, max(1, (int) $request->query('limit', 50))));
        if ($watch) {
            $q->whereIn('media_id', Watch::where('user_id', $user->id)->select('media_id'));
        }
        if ($cats = Categories::expand(array_filter(explode(',', (string) $request->query('cat'))))) {
            $q->whereIn('category_id', $cats);
        }
        $site = \App\Support\Brand::name();
        $items = '';
        foreach ($q->get() as $r) {
            $url = \App\Support\Xml::esc(url('/api') . '?t=get&id=' . $r->guid . '&apikey=' . $user->api_key);
            $items .= '<item><title>' . \App\Support\Xml::esc($r->name) . '</title><guid isPermaLink="false">' . $r->guid . '</guid>'
                . '<link>' . $url . '</link><pubDate>' . ($r->posted_at ?? $r->created_at)->toRssString() . '</pubDate>'
                . '<category>' . \App\Support\Xml::esc(Categories::name($r->category_id)) . '</category>'
                . ($r->media ? '<description>' . \App\Support\Xml::esc($r->media->title . ($r->media->year ? " ({$r->media->year})" : '')) . '</description>' : '')
                . '<enclosure url="' . $url . '" length="' . (int) $r->size . '" type="application/x-nzb"/></item>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>' . \App\Support\Xml::esc($site . ($watch ? ' – Merkliste' : '')) . '</title>'
            . '<link>' . \App\Support\Xml::esc(url('/')) . '</link><description>' . \App\Support\Xml::esc($site) . '</description><language>de-de</language>' . $items . '</channel></rss>';
        return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
