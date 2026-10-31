<?php
namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseReport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request, Release $release)
    {
        $data = $request->validate([
            'reason' => 'required|in:' . implode(',', array_keys(ReleaseReport::REASONS)),
            'note' => 'nullable|string|max:500',
        ]);
        ReleaseReport::updateOrCreate(
            ['release_id' => $release->id, 'user_id' => $request->user()->id],
            ['reason' => $data['reason'], 'note' => $data['note'] ?? null, 'status' => 'open']
        );
        return back()->with('status', 'Danke, die Meldung ist beim Admin angekommen.');
    }
}
