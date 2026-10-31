<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Support\Registration;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function index()
    {
        return view('admin.invites', [
            'invites' => Invite::with('user:id,name')->orderByRaw('used_by is not null')->orderByDesc('id')->paginate(50),
            'mode' => Registration::mode(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['count' => 'required|integer|min:1|max:50', 'note' => 'nullable|string|max:255']);
        for ($i = 0; $i < (int) $data['count']; $i++) {
            Invite::create(['code' => Invite::newCode(), 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
        }
        return back()->with('status', $data['count'] . ' Code(s) erzeugt.');
    }

    public function destroy(Invite $invite)
    {
        $invite->delete();
        return back();
    }
}
