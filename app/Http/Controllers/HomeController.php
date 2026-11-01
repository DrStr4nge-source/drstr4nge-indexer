<?php
namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Release;
use Illuminate\Http\Request;

/** Startseite nach dem Login: neue Filme, neue Serien, neueste Releases. */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('dashboard', [
            'user' => $request->user(),
            // Für alle User gleich: eine Minute zwischenspeichern
            'movies' => \Illuminate\Support\Facades\Cache::remember('home.movies', 60, fn () => $this->latest('movie')),
            'shows' => \Illuminate\Support\Facades\Cache::remember('home.shows', 60, fn () => $this->latest('tv')),
            'releases' => Release::lite()->visible()->with('media')->orderByDesc('posted_at')->limit(15)->get(),
            'hasMedia' => Media::query()->exists(),
        ]);
    }

    /** Filme oder Serien, sortiert nach ihrem neuesten sichtbaren Release. */
    private function latest(string $type)
    {
        return Media::query()
            ->where('type', $type)
            ->whereHas('releases', fn ($q) => $q->visible())
            ->withMax(['releases as last_posted' => fn ($q) => $q->visible()], 'posted_at')
            ->orderByDesc('last_posted')
            ->limit(24)
            ->get();
    }
}
