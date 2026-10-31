<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Models\ReleaseReport;
use Illuminate\Http\Request;

class ReportAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status') === 'done' ? 'done' : 'open';
        $reports = ReleaseReport::where('status', $status)->with(['release', 'user'])->latest()->paginate(50)->withQueryString();
        // Wie oft wurde jedes Release gemeldet?
        $counts = ReleaseReport::whereIn('release_id', $reports->pluck('release_id'))->where('status', 'open')
            ->selectRaw('release_id, count(*) as n')->groupBy('release_id')->pluck('n', 'release_id');
        return view('admin.reports', compact('reports', 'status', 'counts'));
    }

    public function act(Request $request, ReleaseReport $report)
    {
        $action = $request->validate(['action' => 'required|in:done,reopen,spam,delete'])['action'];
        $rel = $report->release;
        $all = ReleaseReport::where('release_id', $report->release_id);
        $msg = match ($action) {
            'done' => tap('Erledigt.', fn () => $report->update(['status' => 'done'])),
            'reopen' => tap('Wieder offen.', fn () => $report->update(['status' => 'open'])),
            'spam' => tap('Release als Spam markiert, Meldungen erledigt.', function () use ($rel, $all) {
                $rel?->forceFill(['status' => 'spam', 'locked' => true])->save();
                $all->update(['status' => 'done']);
            }),
            'delete' => tap('Release gelöscht, Meldungen erledigt.', function () use ($rel, $all) {
                $rel?->delete();
                $all->update(['status' => 'done']);
            }),
        };
        return back()->with('status', $msg);
    }
}
