<?php
namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Release;
use App\Models\User;
use App\Services\Categories;
use App\Services\Nzb;
use App\Services\Search\ReleaseSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Newznab-kompatible API: /api?t=caps|search|tvsearch|movie|get&apikey=... */
class NewznabController extends Controller
{
    public function __invoke(Request $request)
    {
        $t = $request->query('t');
        if ($t === 'caps') {
            return $this->xml($this->caps());
        }

        $user = User::where('api_key', (string) $request->query('apikey'))->first();
        if (!$user || $user->is_banned) {
            return $this->xml('<error code="100" description="Incorrect user credentials"/>', 401);
        }

        // Rate-Limit pro User und Minute
        $key = 'api:' . $user->id;
        $max = $user->isVip() ? config('indexer.api_limit_vip') : config('indexer.api_limit');
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return $this->xml('<error code="500" description="Request limit reached"/>', 429);
        }
        RateLimiter::hit($key, 60);

        if ($t === 'get') {
            $r = Release::visible()->where('guid', (string) $request->query('id'))->with('newsgroup')->firstOrFail();
            return Nzb::download($user, $r);
        }

        if (in_array($t, ['search', 'tvsearch', 'movie'], true)) {
            return $this->xml($this->search($request, $user, $t));
        }

        return $this->xml('<error code="202" description="No such function"/>', 400);
    }

    private function search(Request $request, User $user, string $t): string
    {
        $q = trim((string) $request->query('q'));
        if ($t === 'tvsearch') {
            $season = $request->query('season');
            $ep = $request->query('ep');
            if (is_numeric($season) && (int) $season < 100) {
                $q = trim($q . ' ' . sprintf('S%02d', $season) . (is_numeric($ep) ? sprintf('E%02d', $ep) : ''));
            }
        }

        // Suche über IDs (Sonarr/Radarr): imdbid, tmdbid, tvdbid
        $ids = array_filter([
            'imdb_id' => $this->imdb((string) $request->query('imdbid')),
            'tmdb_id' => ctype_digit((string) $request->query('tmdbid')) ? (int) $request->query('tmdbid') : null,
            'tvdb_id' => ctype_digit((string) $request->query('tvdbid')) ? (int) $request->query('tvdbid') : null,
            'tvmaze_id' => ctype_digit((string) $request->query('tvmazeid')) ? (int) $request->query('tvmazeid') : null,
        ]);
        $extra = $ids ? function ($query) use ($ids) {
            foreach ($ids as $col => $val) {
                $query->whereIn('media_id', Media::where($col, $val)->select('id'));
            }
        } : null;

        $limit = min(100, max(1, (int) $request->query('limit', 50)));
        $offset = max(0, (int) $request->query('offset', 0));
        $res = ReleaseSearch::run(
            $q,
            Categories::expand(array_filter(explode(',', (string) $request->query('cat')))),
            is_numeric($request->query('maxage')) ? (int) $request->query('maxage') : null,
            $offset,
            $limit,
            $extra
        );
        $rows = $res['rows'];
        $total = $res['total'];

        $items = '';
        foreach ($rows as $r) {
            $m = $r->media;
            $meta = $m ? ($m->imdb_id ? '<newznab:attr name="imdb" value="' . e(substr($m->imdb_id, 2)) . '"/>' : '')
                . ($m->tmdb_id ? '<newznab:attr name="tmdbid" value="' . $m->tmdb_id . '"/>' : '')
                . ($m->tvdb_id ? '<newznab:attr name="tvdbid" value="' . $m->tvdb_id . '"/>' : '')
                . ($m->tvmaze_id ? '<newznab:attr name="tvmazeid" value="' . $m->tvmaze_id . '"/>' : '')
                . ($m->coverUrl() ? '<newznab:attr name="coverurl" value="' . e($m->coverUrl()) . '"/>' : '') : '';
            $url = e(url('/api') . '?t=get&id=' . $r->guid . '&apikey=' . $user->api_key);
            $items .= '<item><title>' . e($r->name) . '</title><guid isPermaLink="false">' . $r->guid . '</guid>'
                . '<link>' . $url . '</link><pubDate>' . $r->posted_at->toRssString() . '</pubDate>'
                . '<category>' . e(Categories::name($r->category_id)) . '</category>'
                . '<enclosure url="' . $url . '" length="' . $r->size . '" type="application/x-nzb"/>'
                . '<newznab:attr name="category" value="' . Categories::parent($r->category_id) . '"/>'
                . ($r->category_id !== Categories::parent($r->category_id) ? '<newznab:attr name="category" value="' . $r->category_id . '"/>' : '')
                . $meta
                . '<newznab:attr name="size" value="' . $r->size . '"/></item>';
        }
        return '<rss version="2.0" xmlns:newznab="http://www.newznab.com/DTD/2010/feeds/attributes/"><channel>'
            . '<title>' . e(config('app.name')) . '</title>'
            . '<newznab:response offset="' . $offset . '" total="' . $total . '"/>' . $items . '</channel></rss>';
    }

    private function caps(): string
    {
        $cats = '';
        foreach (config('indexer.categories') as $id => [$name, $parent]) {
            if ($parent !== null) {
                continue;
            }
            $cats .= '<category id="' . $id . '" name="' . e($name) . '">';
            foreach (config('indexer.categories') as $sid => [$sname, $sparent]) {
                if ($sparent === $id) {
                    $cats .= '<subcat id="' . $sid . '" name="' . e(substr(strrchr($sname, '/'), 1)) . '"/>';
                }
            }
            $cats .= '</category>';
        }
        return '<caps><server title="' . e(config('app.name')) . '"/><limits max="100" default="50"/><searching>'
            . '<search available="yes" supportedParams="q"/>'
            . '<tv-search available="yes" supportedParams="q,season,ep,tvdbid,tvmazeid,tmdbid,imdbid"/>'
            . '<movie-search available="yes" supportedParams="q,imdbid,tmdbid"/>'
            . '</searching><categories>' . $cats . '</categories></caps>';
    }

    /** "tt0133093", "0133093" oder "133093" => "tt0133093"; sonst null. */
    private function imdb(string $v): ?string
    {
        $n = preg_replace('/^tt/i', '', trim($v));
        return ctype_digit($n) && $n !== '' ? 'tt' . str_pad($n, 7, '0', STR_PAD_LEFT) : null;
    }

    private function xml(string $body, int $status = 200)
    {
        return response('<?xml version="1.0" encoding="UTF-8"?>' . $body, $status, ['Content-Type' => 'application/xml']);
    }
}
