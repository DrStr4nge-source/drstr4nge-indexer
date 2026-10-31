<x-app-layout>
    <x-slot name="title">PreDB</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Admin</h1></x-slot>
    <div>
        @include('admin._nav')
        <div class="tm-grid cols-4">
            <div class="tm-stat">
                <div class="tm-muted tm-small">IRC-Scraper</div>
                @if (!$configured)
                    <div class="v tm-muted" style="font-size:20px;padding:4px 0">aus</div>
                    <div class="tm-muted tm-small">{{ $irc['enabled'] ? 'Nick fehlt (unten eintragen)' : 'im Admin ausgeschaltet' }}</div>
                @elseif ($connectedAt)
                    <div class="v" style="font-size:20px;padding:4px 0;color:var(--tm-ok)">verbunden</div>
                    <div class="tm-muted tm-small">seit {{ \App\Support\Format::dt($connectedAt, 'd.m.Y H:i') }}</div>
                @else
                    <div class="v" style="font-size:20px;padding:4px 0;color:var(--tm-bad)">getrennt</div>
                    <div class="tm-muted tm-small">Dienst prüfen: systemctl status drstr4nge-pre</div>
                @endif
            </div>
            <div class="tm-stat"><div class="tm-muted tm-small">Letzter Pre</div><div class="v" style="font-size:20px;padding:4px 0">{{ $lastAt ? \App\Support\Format::ago($lastAt) : '–' }}</div><div class="tm-muted tm-small">{{ number_format($today, 0, ',', '.') }} heute · predb.net: {{ !$apiEnabled ? 'aus' : ($apiAt ? \App\Support\Format::ago($apiAt) : 'noch nie') }} · RSS: {{ !$rssEnabled ? 'aus' : ($rssAt ? \App\Support\Format::ago($rssAt) : 'noch nie') }}</div></div>
            <div class="tm-stat"><div class="tm-muted tm-small">Einträge</div><div class="v">{{ number_format($total, 0, ',', '.') }}</div></div>
            <div class="tm-stat"><div class="tm-muted tm-small">Releases mit Pre</div><div class="v">{{ number_format($linked, 0, ',', '.') }}</div></div>
        </div>

        @if ($ircError && !$connectedAt && $configured)
            <div class="tm-alert bad" style="margin-top:16px"><span>Letzter Fehler: {{ $ircError }}</span></div>
        @endif

        <details class="tm-panel" style="margin-top:20px" @if ($errors->hasAny(['irc_server','irc_port','irc_nick','irc_realname','irc_channels','irc_password'])) open @endif>
            <summary style="cursor:pointer;font-weight:600">IRC-Einstellungen</summary>
            <form method="POST" action="{{ route('admin.predb.irc') }}" class="tm-stack" style="margin-top:18px" autocomplete="off">@csrf
                <label class="tm-small" style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" name="irc_enabled" value="1" @checked(old('irc_enabled', $irc['enabled']))> IRC-Scraper eingeschaltet
                </label>
                <div class="tm-grid cols-2">
                    <div>
                        <label class="tm-label" for="irc_server">Server</label>
                        <input class="tm-input" id="irc_server" name="irc_server" value="{{ old('irc_server', $irc['server']) }}" required>
                        @error('irc_server')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                    </div>
                    <div>
                        <label class="tm-label" for="irc_port">Port</label>
                        <div style="display:flex;gap:14px;align-items:center">
                            <input class="tm-input" id="irc_port" name="irc_port" type="number" min="1" max="65535" value="{{ old('irc_port', $irc['port']) }}" required style="max-width:140px">
                            <label class="tm-small" style="display:flex;align-items:center;gap:8px;white-space:nowrap"><input type="checkbox" name="irc_tls" value="1" @checked(old('irc_tls', $irc['tls']))> TLS (meist Port 6697)</label>
                        </div>
                        @error('irc_port')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                    </div>
                    <div>
                        <label class="tm-label" for="irc_nick">Nick</label>
                        <input class="tm-input" id="irc_nick" name="irc_nick" value="{{ old('irc_nick', $irc['nick']) }}" maxlength="30" required>
                        <p class="tm-muted tm-small" style="margin-top:6px">Eindeutig und normal aussehend, sonst droht ein Bann.</p>
                        @error('irc_nick')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                    </div>
                    <div>
                        <label class="tm-label" for="irc_realname">Realname (optional)</label>
                        <input class="tm-input" id="irc_realname" name="irc_realname" value="{{ old('irc_realname', $irc['realname'] !== $irc['nick'] ? $irc['realname'] : '') }}" maxlength="60">
                    </div>
                </div>
                <div>
                    <label class="tm-label" for="irc_channels">Kanäle</label>
                    <textarea class="tm-input" id="irc_channels" name="irc_channels" rows="3" style="height:auto;padding:10px 12px" required>{{ old('irc_channels', implode("\n", $irc['channels'])) }}</textarea>
                    <p class="tm-muted tm-small" style="margin-top:6px">Einer pro Zeile oder mit Komma getrennt. Kanal mit Schlüssel geht hier nicht.</p>
                    @error('irc_channels')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div>
                    <label class="tm-label" for="irc_password">Server-Passwort (optional)</label>
                    <input class="tm-input" id="irc_password" name="irc_password" type="password" value="" placeholder="{{ $hasPassword ? 'gespeichert, leer lassen = behalten' : 'meist nicht nötig, z.B. für ZNC' }}" autocomplete="new-password">
                    @if ($hasPassword)
                        <label class="tm-small tm-muted" style="display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" name="remove_password" value="1"> Passwort entfernen</label>
                    @endif
                    @error('irc_password')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div class="tm-form-row" style="gap:8px;flex-wrap:wrap">
                    <button class="tm-btn tm-btn-primary">Speichern und neu verbinden</button>
                </div>
            </form>
            <div class="tm-form-row" style="gap:8px;flex-wrap:wrap;margin-top:12px">
                <form method="POST" action="{{ route('admin.predb.reconnect') }}">@csrf<button class="tm-btn tm-btn-sm">Nur neu verbinden</button></form>
                <form method="POST" action="{{ route('admin.predb.reset') }}">@csrf<button class="tm-btn tm-btn-sm">Auf .env zurücksetzen</button></form>
            </div>
            <p class="tm-muted tm-small" style="margin-top:12px">Änderungen übernimmt der Dienst <code>drstr4nge-pre</code> nach wenigen Sekunden, ohne Neustart.</p>
        </details>

        <div class="tm-panel" style="margin-top:20px">
            <form method="get" class="tm-form-row" style="margin-bottom:14px;gap:8px">
                <input class="tm-input" type="search" name="q" value="{{ $q }}" placeholder="Releasename suchen" style="flex:1;min-width:0">
                <button class="tm-btn tm-btn-primary">Suchen</button>
            </form>
            <div class="tm-table-wrap">
                <table class="tm-table">
                    <thead><tr><th>Pre</th><th>Release</th><th class="tm-hide-sm">Kategorie</th><th class="num tm-hide-sm">Größe</th><th class="num tm-hide-sm">Dateien</th><th class="tm-hide-sm">Quelle</th></tr></thead>
                    <tbody>
                        @forelse ($pres as $p)
                            <tr>
                                <td class="nowrap tm-muted" title="{{ \App\Support\Format::dt($p->predate, 'd.m.Y H:i:s') }}">{{ \App\Support\Format::ago($p->predate) }}</td>
                                <td>
                                    <div class="tm-relname">{{ $p->title }}</div>
                                    @if ($p->nukeLabel())<div class="tm-meta"><span class="tm-chip {{ $p->isNuked() ? 'bad' : '' }}">{{ $p->nukeLabel() }}</span> <span class="tm-muted tm-small">{{ $p->nuke_reason }}</span></div>@endif
                                </td>
                                <td class="tm-hide-sm nowrap">@if ($p->category)<span class="tm-chip">{{ $p->category }}</span>@endif</td>
                                <td class="num tm-hide-sm nowrap">{{ $p->size ? \App\Support\Format::bytes($p->size) : '' }}</td>
                                <td class="num tm-hide-sm">{{ $p->files }}</td>
                                <td class="tm-hide-sm tm-muted tm-small">{{ $p->source }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="tm-empty" style="margin:8px 0">Noch keine Pre-Daten.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:14px">{{ $pres->links() }}</div>
        </div>
    </div>
</x-app-layout>
