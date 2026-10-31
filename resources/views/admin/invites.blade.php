<x-app-layout>
    <x-slot name="title">Admin: Einladungen</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Einladungen</h1></x-slot>
    <div>
        @include('admin._nav')
        @if ($mode !== 'invite')
            <div class="tm-alert warn" style="margin-bottom:16px">
                <span>Codes werden nur gebraucht, wenn die Registrierung auf „Nur mit Einladungscode“ steht. Aktuell: <b>{{ \App\Support\Registration::MODES[$mode] }}</b>.
                Umstellen unter <a class="tm-link" href="{{ route('admin.settings') }}">Einstellungen</a>.</span>
            </div>
        @endif
        <section class="tm-panel">
            <form method="POST" action="{{ route('admin.invites.store') }}" class="tm-form-row" style="margin-bottom:6px">@csrf
                <input class="tm-input" type="number" name="count" value="1" min="1" max="50" style="width:90px" title="Anzahl">
                <input class="tm-input grow" name="note" placeholder="Notiz, z.B. für wen (optional)" maxlength="255">
                <button class="tm-btn tm-btn-primary">Codes erzeugen</button>
            </form>
            @error('count')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            <div class="tm-table-wrap" style="margin-top:14px">
                <table class="tm-table">
                    <thead><tr><th>Code</th><th class="tm-hide-sm">Notiz</th><th>Status</th><th class="tm-hide-sm">Erstellt</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($invites as $i)
                        <tr>
                            <td class="nowrap"><code>{{ $i->code }}</code></td>
                            <td class="tm-hide-sm tm-muted">{{ $i->note }}</td>
                            <td class="nowrap">
                                @if ($i->used_by)
                                    <span class="tm-chip">benutzt</span> <span class="tm-muted tm-small">{{ $i->user?->name }} · {{ $i->used_at?->format('d.m.Y') }}</span>
                                @else
                                    <span class="tm-chip accent">frei</span>
                                @endif
                            </td>
                            <td class="tm-hide-sm nowrap tm-muted">{{ $i->created_at->format('d.m.Y') }}</td>
                            <td class="num">
                                <div class="tm-form-row" style="justify-content:flex-end;flex-wrap:nowrap;gap:6px">
                                    @unless ($i->used_by)
                                        <button type="button" class="tm-btn tm-btn-sm" data-copy="{{ route('register', ['code' => $i->code]) }}" title="Link zum Registrieren mit diesem Code">Link kopieren</button>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.invites.destroy', $i) }}">@csrf @method('DELETE')<button class="tm-btn tm-btn-sm tm-btn-danger">Löschen</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tm-empty">Noch keine Codes.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $invites->links() }}
        </section>
    </div>
</x-app-layout>
