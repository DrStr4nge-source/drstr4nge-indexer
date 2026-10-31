<x-app-layout>
    <x-slot name="title">Admin: Zwei-Faktor</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Zwei-Faktor-Anmeldung</h1></x-slot>
    <div>
        @include('admin._nav')
        <section class="tm-panel" style="max-width:720px">
            @if ($codes)
                <div class="tm-alert info" style="display:block;margin-bottom:18px">
                    <p style="font-weight:600;margin-bottom:8px">Wiederherstellungscodes, jetzt sicher aufbewahren:</p>
                    <p class="tm-small tm-muted" style="margin-bottom:10px">Jeder Code funktioniert einmal, falls dein Handy weg ist. Sie werden nur jetzt angezeigt.</p>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 18px;font-family:ui-monospace,monospace;font-size:15px">
                        @foreach ($codes as $c)<span>{{ $c }}</span>@endforeach
                    </div>
                </div>
            @endif

            @if ($enabled)
                <p><span class="tm-chip accent">eingeschaltet</span></p>
                <p class="tm-muted tm-small" style="margin-top:10px">Nach jedem Login fragt der Admin-Bereich nach dem 6-stelligen Code aus deiner App. Noch {{ $remaining }} Wiederherstellungscodes übrig.</p>

                <h2 class="tm-h2" style="margin:24px 0 10px;font-size:17px">Neue Wiederherstellungscodes</h2>
                <form method="POST" action="{{ route('admin.2fa.recovery') }}" class="tm-form-row" style="gap:8px;flex-wrap:wrap" autocomplete="off">@csrf
                    <input class="tm-input" name="code" inputmode="numeric" placeholder="aktueller Code aus der App" style="max-width:220px">
                    <button class="tm-btn">Neue Codes erstellen</button>
                </form>
                @error('recovery')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror

                <h2 class="tm-h2" style="margin:24px 0 10px;font-size:17px">Ausschalten</h2>
                <form method="POST" action="{{ route('admin.2fa.disable') }}" class="tm-form-row" style="gap:8px;flex-wrap:wrap" autocomplete="off">@csrf
                    <input class="tm-input" type="password" name="password" placeholder="dein Passwort" style="max-width:220px" autocomplete="current-password">
                    <input class="tm-input" name="code" placeholder="Code oder Wiederherstellungscode" style="max-width:260px">
                    <button class="tm-btn">Ausschalten</button>
                </form>
                @error('disable')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            @elseif ($pending)
                <ol class="tm-small" style="margin:0 0 16px 18px;line-height:1.8">
                    <li>App auf dem Handy öffnen, z.B. Google Authenticator, Aegis, 2FAS oder Microsoft Authenticator.</li>
                    <li>Neues Konto hinzufügen und diesen QR-Code scannen:</li>
                </ol>
                <div style="display:flex;gap:22px;align-items:flex-start;flex-wrap:wrap">
                    <div style="background:#fff;padding:6px;border-radius:10px;line-height:0">{!! $qr !!}</div>
                    <div class="tm-small" style="flex:1;min-width:220px">
                        <p class="tm-muted">Scannen geht nicht? Diesen Schlüssel von Hand eingeben:</p>
                        <p style="font-family:ui-monospace,monospace;font-size:15px;word-break:break-all;margin:6px 0 16px">{{ trim(chunk_split($pending, 4, ' ')) }}</p>
                        <form method="POST" action="{{ route('admin.2fa.confirm') }}" autocomplete="off">@csrf
                            <label class="tm-label" for="code">3. Code aus der App eingeben</label>
                            <div class="tm-form-row" style="gap:8px">
                                <input class="tm-input" id="code" name="code" inputmode="numeric" maxlength="7" autofocus style="max-width:160px;font-size:18px;letter-spacing:2px">
                                <button class="tm-btn tm-btn-primary">Einschalten</button>
                            </div>
                            @error('code')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                        </form>
                    </div>
                </div>
            @else
                <p>Mit Zwei-Faktor reicht dein Passwort allein nicht mehr für den Admin-Bereich. Zusätzlich braucht es einen Code aus einer App auf deinem Handy.</p>
                <form method="POST" action="{{ route('admin.2fa.start') }}" style="margin-top:16px">@csrf<button class="tm-btn tm-btn-primary">Einrichten</button></form>
            @endif
            <p class="tm-muted tm-small" style="margin-top:22px">Handy und Codes verloren? Auf dem Server: <code>php artisan indexer:2fa-reset deine@adresse</code></p>
        </section>
    </div>
</x-app-layout>
