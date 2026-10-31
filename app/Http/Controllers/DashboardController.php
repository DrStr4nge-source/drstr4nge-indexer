<?php
namespace App\Http\Controllers;

use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Konto-Seite: Status, API-Key, letzte Downloads. */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $recent = Download::where('downloads.user_id', $user->id)
            ->join('releases', 'releases.id', '=', 'downloads.release_id')
            ->orderByDesc('downloads.created_at')->limit(10)
            ->get(['releases.name', 'downloads.created_at']);
        return view('account', compact('user', 'recent'));
    }

    public function regenerateKey(Request $request)
    {
        $request->user()->forceFill(['api_key' => Str::random(32)])->save();
        return back()->with('status', 'Neuer API-Key erstellt.');
    }
}
