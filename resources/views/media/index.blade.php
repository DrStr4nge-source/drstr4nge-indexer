@php($tmIsTv = $type === 'tv')
<x-app-layout>
    <x-slot name="title">{{ $tmIsTv ? 'Serien' : 'Filme' }}</x-slot>
    <x-slot name="header">
        <div>
            <div class="tm-eyebrow">Mit Cover und Infos</div>
            <h1 class="tm-h1">{{ $tmIsTv ? 'Serien' : 'Filme' }}</h1>
        </div>
        <div class="tm-muted">{{ number_format($media->total(), 0, ',', '.') }} Titel</div>
    </x-slot>

    <section class="tm-panel" style="padding:14px 16px">
        <form method="GET" class="tm-form-row">
            <input class="tm-input grow" type="search" name="q" value="{{ $search }}" placeholder="{{ $tmIsTv ? 'Serie' : 'Film' }} suchen …">
            @if ($genres->isNotEmpty())
                <select name="genre" class="tm-select" style="width:auto" onchange="this.form.submit()">
                    <option value="">Alle Genres</option>
                    @foreach ($genres as $g)
                        <option value="{{ $g }}" @selected($genre === $g)>{{ $g }}</option>
                    @endforeach
                </select>
            @endif
            <button class="tm-btn tm-btn-primary">Suchen</button>
        </form>
    </section>

    @if ($media->isEmpty())
        <div class="tm-empty">
            @if ($search !== '' || $genre !== '') Nichts gefunden.
            @else Noch keine {{ $tmIsTv ? 'Serien' : 'Filme' }} mit Infos. Sie erscheinen, sobald passende Releases gecrawlt und mit TMDB verknüpft sind.
            @endif
        </div>
    @else
        <div class="tm-mgrid">
            @foreach ($media as $m)
                @php($tmRels = $releases->get($m->id, collect()))
                <article class="tm-mcard">
                    <div class="tm-mcard-top">
                        <a href="{{ route('title', $m) }}" class="tm-mcard-poster">
                            <div class="tm-poster">
                                @if ($m->coverUrl())
                                    <img src="{{ $m->coverUrl() }}" alt="" loading="lazy">
                                @else
                                    <div class="tm-poster-fallback">{{ $m->title }}</div>
                                @endif
                            </div>
                        </a>
                        <div class="tm-mcard-info">
                            <h2 class="tm-mcard-title"><a href="{{ route('title', $m) }}">{{ $m->title }}</a></h2>
                            <div class="tm-meta" style="margin-top:0;gap:10px">
                                @if ($m->year)
                                    <span class="tm-iconline"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>{{ $m->year }}</span>
                                @endif
                                @if ($m->rating)
                                    <span class="tm-iconline tm-star"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>{{ rtrim(rtrim(number_format((float) $m->rating, 1, ',', ''), '0'), ',') }}</span>
                                @endif
                                @if ($m->imdbUrl())<a class="tm-chip accent" href="{{ $m->imdbUrl() }}" target="_blank" rel="noopener">IMDb</a>@endif
                                @if ($m->tmdbUrl())<a class="tm-chip" href="{{ $m->tmdbUrl() }}" target="_blank" rel="noopener">TMDB</a>@endif
                            </div>
                            @if ($m->overview)
                                <p class="tm-clamp">{{ $m->overview }}</p>
                            @endif
                            <dl class="tm-facts">
                                @if ($m->genres)<div><dt>Genre:</dt> <dd>{{ $m->genres }}</dd></div>@endif
                                @if ($m->director)<div><dt>{{ $tmIsTv ? 'Erfinder:' : 'Regie:' }}</dt> <dd>{{ $m->director }}</dd></div>@endif
                                @if ($m->actors)<div><dt>Darsteller:</dt> <dd>{{ $m->actors }}</dd></div>@endif
                            </dl>
                        </div>
                    </div>

                    <div class="tm-mcard-rels">
                        <div class="tm-eyebrow" style="margin-bottom:10px">Verfügbare Releases</div>
                        @foreach ($tmRels->take(\App\Http\Controllers\MediaListController::RELEASES_PER_CARD) as $r)
                            <div class="tm-relbox">
                                <div class="tm-relname">{{ $r->name }}</div>
                                <div class="tm-meta">
                                    <span class="tm-chip">{{ \App\Support\Format::bytes($r->size) }}</span>
                                    <span class="tm-chip">{{ $r->posted_at?->format('d.m.Y H:i') }}</span>
                                    <span class="tm-chip">{{ $r->posted_at?->locale('de')->diffForHumans() }}</span>
                                    @if ($r->pre)<span class="tm-chip accent" title="Pre: {{ $r->pre->predate?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}">Pre {{ \App\Support\Format::ago($r->pre->predate) }}</span>@endif
                                    @if ($r->pre?->isNuked())<span class="tm-chip bad" title="{{ $r->pre->nuke_reason }}">{{ $r->pre->nukeLabel() }}</span>@endif
                                </div>
                                <div class="tm-form-row" style="margin-top:10px;gap:8px">
                                    <a class="tm-btn tm-btn-primary tm-btn-sm" href="{{ route('download', $r->guid) }}">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                                        Download
                                    </a>
                                    <a class="tm-btn tm-btn-sm" href="{{ route('title', $m) }}">Details</a>
                                </div>
                            </div>
                        @endforeach
                        @if ($tmRels->count() > \App\Http\Controllers\MediaListController::RELEASES_PER_CARD)
                            <a class="tm-link tm-small" href="{{ route('title', $m) }}">+ {{ $tmRels->count() - \App\Http\Controllers\MediaListController::RELEASES_PER_CARD }} weitere Releases</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        {{ $media->links() }}
    @endif
</x-app-layout>
