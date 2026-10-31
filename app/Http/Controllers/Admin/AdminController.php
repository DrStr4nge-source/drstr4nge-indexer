<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use App\Models\Download;
use App\Models\Newsgroup;
use App\Models\Payment;
use App\Models\Release;
use App\Models\User;
use App\Support\SystemStats;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'users' => User::count(),
            'vips' => User::where('vip_until', '>', now())->count(),
            'releases' => Release::count(),
            'groups' => Newsgroup::where('active', true)->count(),
            'grabs' => Download::where('created_at', '>=', now()->startOfDay())->count(),
            'income' => Payment::where('status', 'paid')->sum('amount'),
            'system' => SystemStats::collect(base_path()),
        ]);
    }

    /** Nur die Server-Kacheln, für die automatische Aktualisierung */
    public function system()
    {
        return view('admin._system', ['system' => SystemStats::collect(base_path())]);
    }

    public function users(Request $request)
    {
        $users = User::when($request->query('q'), fn ($q, $s) => $q->where('email', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))
            ->orderByDesc('id')->paginate(50)->withQueryString();
        return view('admin.users', compact('users'));
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'action' => 'required|in:add_vip,remove_vip,ban,unban,make_admin,remove_admin',
            'days' => 'nullable|integer|min:1|max:3650',
        ]);
        $days = (int) ($data['days'] ?? 30); // Formularwerte kommen als Text, Carbon 3 verlangt eine Zahl
        match ($data['action']) {
            'add_vip' => $user->forceFill(['vip_until' => ($user->isVip() ? $user->vip_until : now())->copy()->addDays($days)])->save(),
            'remove_vip' => $user->forceFill(['vip_until' => null])->save(),
            'ban' => $user->forceFill(['is_banned' => true])->save(),
            'unban' => $user->forceFill(['is_banned' => false])->save(),
            'make_admin' => $user->forceFill(['is_admin' => true])->save(),
            'remove_admin' => $user->id === $request->user()->id ? null : $user->forceFill(['is_admin' => false])->save(),
        };
        return back();
    }

    public function groups()
    {
        return view('admin.groups', ['groups' => Newsgroup::withCount('releases')->orderBy('name')->get()]);
    }

    public function storeGroup(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:newsgroups,name']);
        Newsgroup::create(['name' => trim($data['name'])]);
        return back();
    }

    public function toggleGroup(Newsgroup $group)
    {
        $group->update(['active' => !$group->active]);
        return back();
    }

    /** Beim nächsten Crawl die neuesten INDEXER_BACKFILL Artikel noch einmal lesen (z.B. nach einem Parser-Update). */
    public function resetGroup(Newsgroup $group)
    {
        $group->update(['last_article' => 0]);
        return back()->with('status', "{$group->name} wird beim nächsten Crawl neu eingelesen.");
    }

    public function destroyGroup(Newsgroup $group)
    {
        $group->releases()->select('id')->chunkById(1000, fn ($c) => \App\Services\Search\ReleaseIndex::remove($c->pluck('id')->all()));
        $group->delete();
        return back();
    }

    public function payments()
    {
        return view('admin.payments', ['payments' => Payment::with('user')->orderByDesc('id')->paginate(50)]);
    }
    /** Gemeinsame Filter für Liste und Mehrfach-Aktionen. */
    private function releaseQuery(string $filter, ?string $search)
    {
        $q = Release::query();
        match ($filter) {
            'spam' => $q->where('status', 'spam'),
            'passworded' => $q->where('status', 'passworded'),
            'obfuscated' => $q->where('obfuscated', true)->where('completion', '>=', config('indexer.min_completion')),
            'incomplete' => $q->where('completion', '<', config('indexer.min_completion')),
            'all' => null,
            default => $q->where(fn ($w) => $w->whereIn('status', ['spam', 'passworded'])->orWhere('obfuscated', true)),
        };
        if (($search = trim((string) $search)) !== '') {
            $like = '%' . addcslashes($search, '%_') . '%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('poster', 'like', $like));
        }
        return $q;
    }

    public function releases(Request $request)
    {
        $filter = (string) $request->query('status', 'flagged');
        $search = (string) $request->query('q', '');
        $perPage = in_array((int) $request->query('per_page'), [50, 100, 250], true) ? (int) $request->query('per_page') : 50;
        $releases = $this->releaseQuery($filter, $search)->with('newsgroup:id,name')
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        return view('admin.releases', compact('releases', 'filter', 'search', 'perPage'));
    }

    /** Mehrfach-Aktion: auf ausgewählte Releases, auf eine einzelne Zeile oder auf alle Treffer des Filters. */
    public function releasesBulk(Request $request)
    {
        if ($row = $request->input('row')) {
            // Knopf in einer einzelnen Zeile: "aktion:id"
            [$action, $id] = array_pad(explode(':', (string) $row, 2), 2, null);
            $request->merge(['action' => $action, 'ids' => [$id], 'scope' => 'selected']);
        }
        $data = $request->validate([
            'action' => 'required|in:ok,spam,delete,blacklist',
            'scope' => 'required|in:selected,all',
            'ids' => 'array|required_if:scope,selected',
            'ids.*' => 'integer',
            'status' => 'nullable|string|max:20',
            'q' => 'nullable|string|max:255',
        ], ['ids.required_if' => 'Bitte mindestens ein Release auswählen.']);

        $query = $data['scope'] === 'all'
            ? $this->releaseQuery($data['status'] ?? 'flagged', $data['q'] ?? '')
            : Release::whereIn('id', $data['ids']);

        $count = 0;
        if ($data['action'] === 'blacklist') {
            $posters = (clone $query)->whereNotNull('poster')->distinct()->pluck('poster')->filter()->take(500);
            foreach ($posters as $poster) {
                Blacklist::firstOrCreate(['type' => 'poster', 'pattern' => mb_substr($poster, 0, 255)]);
            }
            Blacklist::flush();
            // alle Releases dieser Absender als Spam markieren, außer von Hand freigegebene
            $query = Release::whereIn('poster', $posters->all())->where(fn ($w) => $w->where('locked', false)->orWhere('status', '!=', 'ok'));
            $data['action'] = 'spam';
            $msg = $posters->count() . ' Poster gesperrt, ';
        }

        // Einzeln speichern/löschen, damit der Suchindex mitgezogen wird
        (clone $query)->select('*')->chunkById(500, function ($chunk) use ($data, &$count) {
            foreach ($chunk as $r) {
                match ($data['action']) {
                    'ok' => $r->forceFill(['status' => 'ok', 'obfuscated' => false, 'locked' => true])->save(),
                    'spam' => $r->forceFill(['status' => 'spam', 'locked' => true])->save(),
                    'delete' => $r->delete(),
                };
                $count++;
            }
        });

        $label = ['ok' => 'freigegeben', 'spam' => 'als Spam markiert', 'delete' => 'gelöscht'][$data['action']];
        return back()->with('status', ($msg ?? '') . "$count Releases $label.");
    }

    public function blacklist()
    {
        return view('admin.blacklist', ['rules' => Blacklist::orderByDesc('id')->get()]);
    }

    public function storeBlacklist(Request $request)
    {
        $data = $request->validate(['type' => 'required|in:poster,name_regex', 'pattern' => 'required|string|max:255']);
        if ($data['type'] === 'name_regex' && !Blacklist::validRegex($data['pattern'])) {
            return back()->withErrors(['pattern' => 'Ungültiger regulärer Ausdruck.']);
        }
        Blacklist::create($data);
        Blacklist::flush();
        return back();
    }

    public function destroyBlacklist(Blacklist $rule)
    {
        $rule->delete();
        Blacklist::flush();
        return back();
    }
}
