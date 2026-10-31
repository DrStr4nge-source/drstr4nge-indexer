<x-app-layout>
    <x-slot name="title">Admin: Meldungen</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Meldungen</h1></x-slot>
    <div>
        @include('admin._nav')
        <div class="tm-tabs" style="margin-top:-6px">
            <a href="{{ route('admin.reports') }}" @class(['is-active' => $status === 'open'])>Offen</a>
            <a href="{{ route('admin.reports', ['status' => 'done']) }}" @class(['is-active' => $status === 'done'])>Erledigt</a>
        </div>
        <section class="tm-panel">
            <div class="tm-table-wrap">
                <table class="tm-table">
                    <thead><tr><th>Release</th><th>Grund</th><th class="tm-hide-sm">Von</th><th class="tm-hide-sm">Wann</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($reports as $r)
                        <tr>
                            <td>
                                @if ($r->release)
                                    <div class="tm-relname">{{ $r->release->name }}</div>
                                    <div class="tm-meta">{{ \App\Support\Format::bytes($r->release->size) }} · {{ $r->release->status }}
                                        @if (($counts[$r->release_id] ?? 0) > 1)<span class="tm-chip bad">{{ $counts[$r->release_id] }}× gemeldet</span>@endif
                                    </div>
                                @else
                                    <span class="tm-muted">Release gelöscht</span>
                                @endif
                            </td>
                            <td><span class="tm-chip">{{ \App\Models\ReleaseReport::REASONS[$r->reason] ?? $r->reason }}</span>@if ($r->note)<div class="tm-muted tm-small" style="margin-top:4px">{{ $r->note }}</div>@endif</td>
                            <td class="tm-hide-sm tm-small">{{ $r->user?->name ?? '–' }}</td>
                            <td class="tm-hide-sm nowrap tm-muted tm-small">{{ \App\Support\Format::ago($r->created_at) }}</td>
                            <td class="num">
                                <form method="POST" action="{{ route('admin.reports.act', $r) }}" style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">@csrf
                                    @if ($status === 'open')
                                        <button name="action" value="done" class="tm-btn tm-btn-sm">Erledigt</button>
                                        @if ($r->release)
                                            <button name="action" value="spam" class="tm-btn tm-btn-sm">Spam</button>
                                            <button name="action" value="delete" class="tm-btn tm-btn-sm" onclick="return confirm('Release wirklich löschen?')">Löschen</button>
                                        @endif
                                    @else
                                        <button name="action" value="reopen" class="tm-btn tm-btn-sm">Wieder öffnen</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tm-empty" style="margin:8px 0">{{ $status === 'open' ? 'Keine offenen Meldungen.' : 'Noch nichts erledigt.' }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:14px">{{ $reports->links() }}</div>
        </section>
    </div>
</x-app-layout>
