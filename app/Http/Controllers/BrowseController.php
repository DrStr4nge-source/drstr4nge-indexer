<?php
namespace App\Http\Controllers;

use App\Models\Release;
use App\Services\Categories;
use App\Services\Nzb;
use App\Services\Search\ReleaseSearch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class BrowseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 50;
        $page = max(1, (int) $request->query('page', 1));
        $res = ReleaseSearch::run(
            $request->query('q'),
            Categories::expand(array_filter([$request->query('cat')])),
            null,
            ($page - 1) * $perPage,
            $perPage
        );
        $releases = (new LengthAwarePaginator($res['rows'], $res['total'], $perPage, $page, ['path' => $request->url()]))
            ->withQueryString();
        $parents = collect(config('indexer.categories'))->filter(fn ($c) => $c[1] === null)->map(fn ($c) => $c[0]);
        return view('browse', compact('releases', 'parents'));
    }

    public function download(Request $request, string $guid)
    {
        $release = Release::visible()->where('guid', $guid)->with('newsgroup')->firstOrFail();
        return Nzb::download($request->user(), $release);
    }
}
