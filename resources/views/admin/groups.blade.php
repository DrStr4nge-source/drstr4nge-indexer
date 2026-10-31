<x-app-layout>
    <x-slot name="title">Admin: Newsgroups</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Newsgroups</h1></x-slot>
    <div>
        @include('admin._nav')
        <section class="tm-panel">
            <form method="POST" action="{{ route('admin.groups.store') }}" class="tm-form-row" style="margin-bottom:6px">@csrf
                <input class="tm-input grow" name="name" placeholder="z.B. alt.binaries.example" required>
                <button class="tm-btn tm-btn-primary">Hinzufügen</button>
            </form>
            @error('name')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            <div class="tm-table-wrap" style="margin-top:14px">
                <table class="tm-table">
                    <thead><tr><th>Gruppe</th><th class="num">Releases</th><th class="num tm-hide-sm">Bis Artikel</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($groups as $g)
                        <tr>
                            <td>{{ $g->name }}</td>
                            <td class="num">{{ number_format($g->releases_count, 0, ',', '.') }}</td>
                            <td class="num tm-hide-sm tm-muted">{{ number_format($g->last_article, 0, ',', '.') }}</td>
                            <td>@if ($g->active)<span class="tm-chip accent">aktiv</span>@else<span class="tm-chip">pausiert</span>@endif</td>
                            <td class="num">
                                <div class="tm-form-row" style="justify-content:flex-end;flex-wrap:nowrap">
                                    <form method="POST" action="{{ route('admin.groups.toggle', $g) }}">@csrf<button class="tm-btn tm-btn-sm">{{ $g->active ? 'Pausieren' : 'Aktivieren' }}</button></form>
                                    <form method="POST" action="{{ route('admin.groups.reset', $g) }}" onsubmit="return confirm('Die neuesten Artikel dieser Gruppe beim nächsten Crawl noch einmal einlesen?')">@csrf<button class="tm-btn tm-btn-sm" title="Nach einem Update: Artikel noch einmal lesen">Neu einlesen</button></form>
                                    <form method="POST" action="{{ route('admin.groups.destroy', $g) }}" onsubmit="return confirm('Gruppe und alle Releases löschen?')">@csrf @method('DELETE')<button class="tm-btn tm-btn-sm tm-btn-danger">Löschen</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tm-empty">Noch keine Newsgroups. Trage oben die erste ein.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
