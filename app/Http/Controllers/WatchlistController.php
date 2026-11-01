<?php
namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Release;
use App\Models\Watch;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = Watch::where('user_id', $user->id)->with('media')->latest()->get()->filter(fn ($w) => $w->media);
        $releases = Release::lite()->visible()->whereIn('media_id', $items->pluck('media_id'))->with('media')->orderByDesc('posted_at')->paginate(50);
        return view('watchlist', compact('items', 'releases', 'user'));
    }

    public function toggle(Request $request, Media $media)
    {
        $user = $request->user();
        $w = Watch::where('user_id', $user->id)->where('media_id', $media->id)->first();
        if ($w) {
            $w->delete();
            $msg = "„{$media->title}“ von der Merkliste entfernt.";
        } else {
            if (Watch::where('user_id', $user->id)->count() >= 500) {
                return back()->with('status', 'Die Merkliste ist voll (500 Titel).');
            }
            Watch::create(['user_id' => $user->id, 'media_id' => $media->id]);
            $msg = "„{$media->title}“ ist auf deiner Merkliste.";
        }
        return back()->with('status', $msg);
    }
}
