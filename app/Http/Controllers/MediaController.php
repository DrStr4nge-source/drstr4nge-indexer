<?php
namespace App\Http\Controllers;

use App\Models\Media;

/** Detailseite eines Films oder einer Serie mit allen passenden Releases. */
class MediaController extends Controller
{
    public function show(Media $media)
    {
        $releases = $media->releases()->lite()->visible()->with('media')
            ->orderByDesc('posted_at')->paginate(50);
        abort_if($releases->total() === 0, 404);

        return view('title', compact('media', 'releases'));
    }
}
