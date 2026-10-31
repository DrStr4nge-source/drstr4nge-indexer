<x-app-layout>
    <x-slot name="title">Admin: Releases</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Releases prüfen</h1></x-slot>
    <div>
        @include('admin._nav')
        <section class="tm-panel">
            <div class="tm-tabs">
                @foreach (['flagged' => 'Auffällige', 'obfuscated' => 'Verschleiert', 'spam' => 'Spam', 'passworded' => 'Passwort', 'incomplete' => 'Unvollständig', 'all' => 'Alle'] as $k => $label)
                    <a href="{{ route('admin.releases', array_filter(['status' => $k, 'q' => $search, 'per_page' => $perPage != 50 ? $perPage : null])) }}" @class(['is-active' => $filter === $k])>{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" class="tm-form-row" style="margin-bottom:14px">
                <input type="hidden" name="status" value="{{ $filter }}">
                <input class="tm-input grow" type="search" name="q" value="{{ $search }}" placeholder="Name oder Poster suchen">
                <select name="per_page" class="tm-select" style="width:auto" onchange="this.form.submit()">
                    @foreach ([50, 100, 250] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} pro Seite</option>
                    @endforeach
                </select>
                <button class="tm-btn">Suchen</button>
            </form>

            @error('ids')<div class="tm-alert bad" style="margin-bottom:12px">{{ $message }}</div>@enderror

            <form method="POST" action="{{ route('admin.releases.bulk') }}" id="tm-bulk">@csrf
                <input type="hidden" name="status" value="{{ $filter }}">
                <input type="hidden" name="q" value="{{ $search }}">

                <div class="tm-form-row" style="margin-bottom:12px;padding:10px 12px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-bg-2)">
                    <span class="tm-small"><b id="tm-sel-count">0</b> ausgewählt</span>
                    <select name="action" class="tm-select tm-input-sm" style="width:auto">
                        <option value="delete">Löschen</option>
                        <option value="spam">Als Spam markieren</option>
                        <option value="ok">Freigeben</option>
                        <option value="blacklist">Poster sperren (Blacklist + alle als Spam)</option>
                    </select>
                    <button class="tm-btn tm-btn-sm tm-btn-primary" name="scope" value="selected" onclick="return tmBulkSelected()">Auf Auswahl anwenden</button>
                    @if ($releases->total() > 0)
                        <button class="tm-btn tm-btn-sm" name="scope" value="all" style="margin-left:auto"
                            onclick="return confirm('Aktion auf ALLE {{ number_format($releases->total(), 0, ',', '.') }} Treffer dieses Filters anwenden?')">
                            Auf alle {{ number_format($releases->total(), 0, ',', '.') }} Treffer anwenden
                        </button>
                    @endif
                </div>

                <div class="tm-table-wrap">
                    <table class="tm-table">
                        <thead>
                            <tr>
                                <th style="width:34px"><input type="checkbox" id="tm-check-all" title="Alle auf dieser Seite auswählen"></th>
                                <th>Release</th>
                                <th class="tm-hide-sm">Kategorie</th>
                                <th>Status</th>
                                <th class="tm-hide-sm">Poster</th>
                                <th class="num">Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($releases as $r)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $r->id }}" class="tm-check"></td>
                                <td><div class="tm-relname">{{ $r->name }}</div><div class="tm-meta">{{ $r->completion }}% · {{ \App\Support\Format::bytes($r->size) }} · {{ $r->newsgroup?->name }}</div></td>
                                <td class="tm-hide-sm nowrap"><span class="tm-chip">{{ \App\Services\Categories::name($r->category_id) }}</span></td>
                                <td class="nowrap">{{ $r->status }}@if ($r->obfuscated) · verschleiert @endif @if ($r->locked) <span class="tm-muted">(manuell)</span> @endif</td>
                                <td class="tm-hide-sm tm-relname tm-muted">{{ $r->poster }}</td>
                                <td class="num">
                                    <div class="tm-form-row" style="justify-content:flex-end;flex-wrap:nowrap;gap:6px">
                                        <button class="tm-btn tm-btn-sm" name="row" value="ok:{{ $r->id }}" title="Freigeben">&#10003;</button>
                                        <button class="tm-btn tm-btn-sm" name="row" value="spam:{{ $r->id }}" title="Als Spam">Spam</button>
                                        <button class="tm-btn tm-btn-sm tm-btn-danger" name="row" value="delete:{{ $r->id }}" title="Löschen">&#10005;</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="tm-empty">Nichts gefunden.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
            {{ $releases->links() }}
        </section>
    </div>
    <script>
        (function () {
            var all = document.getElementById('tm-check-all');
            var boxes = function () { return document.querySelectorAll('.tm-check'); };
            var update = function () {
                var n = document.querySelectorAll('.tm-check:checked').length;
                document.getElementById('tm-sel-count').textContent = n;
                if (all) { all.checked = n > 0 && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length; }
            };
            if (all) all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; }); update(); });
            document.addEventListener('change', function (e) { if (e.target.classList.contains('tm-check')) update(); });
            // Shift+Klick wählt einen Bereich
            var last = null;
            document.addEventListener('click', function (e) {
                if (!e.target.classList.contains('tm-check')) return;
                var list = Array.prototype.slice.call(boxes());
                if (e.shiftKey && last) {
                    var a = list.indexOf(last), b = list.indexOf(e.target);
                    list.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { x.checked = e.target.checked; });
                    update();
                }
                last = e.target;
            });
            window.tmBulkSelected = function () {
                var n = document.querySelectorAll('.tm-check:checked').length;
                if (!n) { alert('Bitte mindestens ein Release auswählen.'); return false; }
                var action = document.querySelector('#tm-bulk select[name=action]');
                return confirm('"' + action.options[action.selectedIndex].text + '" auf ' + n + ' Releases anwenden?');
            };
            update();
        })();
    </script>
</x-app-layout>
