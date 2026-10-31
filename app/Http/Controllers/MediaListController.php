<?php
namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Release;
use Illuminate\Http\Request;

/** Filme bzw. Serien als Karten mit Cover, Infos und den verfügbaren Releases. */
class MediaListController extends Controller
{
    public const RELEASES_PER_CARD = 4;

    public function movies(Request $request)
    {
        return $this->list($request, 'movie');
    }

    public function shows(Request $request)
    {
        return $this->list($request, 'tv');
    }

    private function list(Request $request, string $type)
    {
        $search = trim((string) $request->query('q', ''));
        $genre = trim((string) $request->query('genre', ''));

        $media = Media::query()
            ->where('type', $type)
            ->whereHas('releases', fn ($q) => $q->visible())
            ->withMax(['releases as last_posted' => fn ($q) => $q->visible()], 'posted_at')
            ->withCount(['releases as release_count' => fn ($q) => $q->visible()])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%' . addcslashes($search, '%_') . '%'))
            ->when($genre !== '', fn ($q) => $q->where('genres', 'like', '%' . addcslashes($genre, '%_') . '%'))
            ->orderByDesc('last_posted')
            ->paginate(20)
            ->withQueryString();

        // Releases nur für die Titel dieser Seite laden, neueste zuerst
        $releases = Release::visible()
            ->whereIn('media_id', $media->pluck('id'))
            ->orderByDesc('posted_at')
            ->get(['id', 'guid', 'media_id', 'name', 'size', 'posted_at', 'category_id'])
            ->groupBy('media_id');

        $genres = Media::where('type', $type)->whereNotNull('genres')->pluck('genres')
            ->flatMap(fn ($g) => array_map('trim', explode(',', $g)))->filter()->unique()->sort()->values();

        return view('media.index', [
            'type' => $type,
            'media' => $media,
            'releases' => $releases,
            'genres' => $genres,
            'search' => $search,
            'genre' => $genre,
        ]);
    }
}
