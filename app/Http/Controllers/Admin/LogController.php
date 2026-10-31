<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LogReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LogController extends Controller
{
    private const FILTERS = ['probleme' => 3, 'fehler' => 4, 'alle' => 1];

    public function index(Request $request)
    {
        $filter = array_key_exists($request->query('stufe'), self::FILTERS) ? $request->query('stufe') : 'probleme';
        $q = trim((string) $request->query('q'));
        $files = LogReader::files(storage_path('logs'));
        return view('admin.log', [
            'entries' => LogReader::entries($files, self::FILTERS[$filter], 300, 4 * 1024 * 1024, mb_substr($q, 0, 100)),
            'filter' => $filter,
            'q' => $q,
            'size' => array_sum(array_map('filesize', $files)),
            'problems' => Cache::get('health.problems', []),
        ]);
    }

    public function clear()
    {
        foreach (LogReader::files(storage_path('logs')) as $f) {
            @file_put_contents($f, '');
        }
        return redirect()->route('admin.log')->with('status', 'Log geleert.');
    }
}
