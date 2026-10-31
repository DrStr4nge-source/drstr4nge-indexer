@props(['releases', 'showCover' => true, 'showTitle' => true])
<div class="tm-table-wrap">
    <table class="tm-table">
        <thead>
            <tr>
                @if ($showCover)<th class="tm-hide-sm"></th>@endif
                <th>Release</th>
                <th class="tm-hide-sm">Kategorie</th>
                <th class="num">Größe</th>
                <th class="tm-hide-sm">Datum</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($releases as $r)
                <tr>
                    @if ($showCover)
                        <td class="tm-hide-sm" style="width:52px">
                            @if ($r->media?->coverUrl())
                                <a href="{{ route('title', $r->media) }}"><img class="tm-thumb" src="{{ $r->media->coverUrl() }}" alt="" loading="lazy"></a>
                            @else
                                <div class="tm-thumb-empty"></div>
                            @endif
                        </td>
                    @endif
                    <td>
                        <div class="tm-relname">{{ $r->name }}</div>
                        @if ($r->pre)
                            <div class="tm-meta">
                                <span class="tm-chip" title="Pre: {{ \App\Support\Format::dt($r->pre->predate, 'd.m.Y H:i') }}{{ $r->pre->source ? ' · ' . $r->pre->source : '' }}">Pre {{ \App\Support\Format::ago($r->pre->predate) }}</span>
                                @if ($r->pre->isNuked())<span class="tm-chip bad" title="{{ $r->pre->nuke_reason }}">{{ $r->pre->nukeLabel() }}{{ $r->pre->nuke_reason ? ': ' . \Illuminate\Support\Str::limit($r->pre->nuke_reason, 40) : '' }}</span>@endif
                            </div>
                        @endif
                        @if ($showTitle && $r->media)
                            <div class="tm-meta">
                                <a class="tm-link" href="{{ route('title', $r->media) }}">{{ $r->media->title }}</a>
                                @if ($r->media->year)<span class="tm-chip">{{ $r->media->year }}</span>@endif
                                @if ($r->media->rating)<span>&#9733; {{ $r->media->rating }}</span>@endif
                            </div>
                        @endif
                    </td>
                    <td class="tm-hide-sm nowrap"><span class="tm-chip">{{ \App\Services\Categories::name($r->category_id) }}</span></td>
                    <td class="num">{{ \App\Support\Format::bytes($r->size) }}</td>
                    <td class="tm-hide-sm nowrap tm-muted">{{ \App\Support\Format::dt($r->posted_at, 'd.m.Y H:i') }}</td>
                    <td class="num nowrap">
                        <a class="tm-btn tm-btn-primary tm-btn-sm" href="{{ route('download', $r->guid) }}">NZB</a>
                        <details class="tm-report">
                            <summary class="tm-btn tm-btn-sm" title="Release melden" aria-label="Release melden">!</summary>
                            <form method="POST" action="{{ route('report', $r->guid) }}" class="tm-report-box">@csrf
                                <div class="tm-small" style="font-weight:600;margin-bottom:8px">Release melden</div>
                                <select class="tm-select" name="reason" required style="height:34px">
                                    @foreach (\App\Models\ReleaseReport::REASONS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                </select>
                                <input class="tm-input" name="note" maxlength="500" placeholder="Hinweis (optional)" style="height:34px;margin-top:6px">
                                <button class="tm-btn tm-btn-primary tm-btn-sm" style="margin-top:8px;width:100%">Melden</button>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="tm-empty" style="margin:8px 0">Nichts gefunden.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
