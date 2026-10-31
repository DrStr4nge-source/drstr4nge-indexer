<x-app-layout>
    <x-slot name="title">Admin: Zugangsdaten</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Zugangsdaten</h1></x-slot>
    <div>
        @include('admin._nav')
        <p class="tm-muted tm-small" style="max-width:820px;margin:0 0 16px">
            Was du hier einträgst, gilt sofort und hat Vorrang vor der <code>.env</code>. Leeres Feld = Wert aus der <code>.env</code>.
            Passwörter werden verschlüsselt gespeichert und nie wieder angezeigt.
        </p>
        @if (session('test_output'))
            <pre class="tm-panel" style="max-width:820px;margin:0 0 16px;white-space:pre-wrap;font-size:12.5px;padding:14px 16px">{{ session('test_output') }}</pre>
        @endif

        <div class="tm-stack" style="max-width:820px">
        @foreach ($groups as $group => $title)
            <section class="tm-panel" id="{{ $group }}">
                <h2 class="tm-h2" style="margin:0 0 14px">{{ $title }}</h2>
                <form method="POST" action="{{ route('admin.credentials.update', $group) }}" autocomplete="off">@csrf
                    <div class="tm-grid cols-2">
                    @foreach ($fields[$group] as $key => $f)
                        <div @if ($f['type'] === 'emails' || $key === 'backup_rclone_remote') style="grid-column:1/-1" @endif>
                            @if ($f['type'] === 'bool')
                                <label class="tm-small" style="display:flex;align-items:center;gap:8px;margin-top:26px">
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $f['value']))> {{ $f['label'] }}
                                </label>
                            @else
                                <label class="tm-label" for="{{ $key }}">{{ $f['label'] }}
                                    @if ($f['from_admin'])<span class="tm-chip accent" style="margin-left:6px">Admin</span>@endif
                                </label>
                                @if (str_starts_with($f['type'], 'in:'))
                                    <select class="tm-select" id="{{ $key }}" name="{{ $key }}">
                                        @foreach (explode(',', substr($f['type'], 3)) as $opt)
                                            <option value="{{ $opt }}" @selected(old($key, $f['value']) === $opt)>{{ ['btcpay' => 'BTCPay Server', 'nowpayments' => 'NOWPayments'][$opt] ?? $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($f['type'] === 'secret')
                                    <input class="tm-input" id="{{ $key }}" name="{{ $key }}" type="password" value="" autocomplete="new-password"
                                        placeholder="{{ $f['has_secret'] ? ($f['from_admin'] ? 'gespeichert, leer lassen = behalten' : 'aus .env, leer lassen = behalten') : 'nicht gesetzt' }}">
                                    @if ($f['from_admin'])
                                        <label class="tm-small tm-muted" style="display:flex;align-items:center;gap:8px;margin-top:6px"><input type="checkbox" name="remove_{{ $key }}" value="1"> Admin-Wert löschen (wieder .env)</label>
                                    @endif
                                @else
                                    <input class="tm-input" id="{{ $key }}" name="{{ $key }}"
                                        type="{{ in_array($f['type'], ['port']) || str_starts_with($f['type'], 'int') ? 'number' : 'text' }}"
                                        value="{{ old($key, is_bool($f['value']) ? '' : $f['value']) }}"
                                        @if ($key === 'backup_rclone_remote') placeholder="wird von Google Drive unten gesetzt, z.B. gdrive:drstr4nge-backups" @endif
                                        @if ($key === 'alert_email') placeholder="leer = an alle Admins, mehrere mit Komma" @endif>
                                @endif
                                @error($key)<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                            @endif
                        </div>
                    @endforeach
                    </div>
                    <div class="tm-form-row" style="gap:8px;flex-wrap:wrap;margin-top:16px">
                        <button class="tm-btn tm-btn-primary">Speichern</button>
                    </div>
                </form>

                @if ($group === 'usenet')
                    <form method="POST" action="{{ route('admin.credentials.test', 'usenet') }}" style="margin-top:10px">@csrf<button class="tm-btn tm-btn-sm">Verbindung testen</button></form>
                @elseif ($group === 'mail')
                    <form method="POST" action="{{ route('admin.credentials.test', 'mail') }}" style="margin-top:10px">@csrf<button class="tm-btn tm-btn-sm">Test-Mail schicken</button></form>
                    <p class="tm-muted tm-small" style="margin-top:8px">Port 587 oder 465. Warnungen kommen, wenn der Crawler steht, der IRC-Scraper getrennt ist, die Festplatte voll läuft oder die Sicherung fehlt.</p>
                @elseif ($group === 'payment')
                    <p class="tm-muted tm-small" style="margin-top:10px">IPN-Adresse bei NOWPayments: <code>{{ rtrim(config('app.url'), '/') }}/nowpayments/ipn (wird automatisch mitgeschickt)</code></p>
                @elseif ($group === 'backup')
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--tm-line)">
                        <div class="tm-form-row" style="gap:10px;flex-wrap:wrap;align-items:center">
                            <span class="tm-small">Letzte Sicherung: <b>{{ $backupAt ? \App\Support\Format::ago(\Illuminate\Support\Carbon::createFromTimestamp($backupAt)) : 'noch keine' }}</b></span>
                            @if ($backupRunning)<span class="tm-chip accent">läuft gerade</span>@endif
                            <form method="POST" action="{{ route('admin.credentials.backup') }}">@csrf<button class="tm-btn tm-btn-sm" @disabled($backupRunning)>Jetzt sichern</button></form>
                        </div>
                        @if ($backupError)<div class="tm-alert bad" style="margin-top:10px"><span>{{ $backupError }}</span></div>@endif
                        <p class="tm-muted tm-small" style="margin-top:8px">Gespeichert in <code>{{ config('indexer.backup.path') }}</code>, täglich um {{ config('indexer.backup.time') }} Uhr.</p>

                        <h3 style="font-weight:600;margin:18px 0 8px">Google Drive</h3>
                        @if (!$rclone)
                            <div class="tm-alert bad"><span>rclone ist nicht installiert. Auf dem Server: <code>sudo apt install rclone</code></span></div>
                        @elseif ($gdrive)
                            <p class="tm-small">Verbunden, Ziel: <code>{{ config('indexer.backup.rclone_remote') }}</code></p>
                            <div class="tm-form-row" style="gap:8px;flex-wrap:wrap;margin-top:8px">
                                <form method="POST" action="{{ route('admin.credentials.test', 'gdrive') }}">@csrf<button class="tm-btn tm-btn-sm">Verbindung testen</button></form>
                                <form method="POST" action="{{ route('admin.credentials.gdrive') }}">@csrf<input type="hidden" name="remove" value="1"><button class="tm-btn tm-btn-sm">Trennen</button></form>
                            </div>
                        @endif
                        @if ($rclone)
                            <details style="margin-top:10px" @if ($errors->hasAny(['token', 'folder']) || !$gdrive) open @endif>
                                <summary class="tm-small" style="cursor:pointer">{{ $gdrive ? 'Neu verbinden' : 'Verbinden' }}</summary>
                                <ol class="tm-small tm-muted" style="margin:10px 0 12px 18px;line-height:1.7">
                                    <li>Auf deinem PC rclone herunterladen: <a class="tm-link" href="https://rclone.org/downloads/" target="_blank" rel="noopener">rclone.org/downloads</a> (Windows: ZIP entpacken)</li>
                                    <li>Im rclone-Ordner eine Eingabeaufforderung öffnen und ausführen: <code>rclone authorize "drive"</code></li>
                                    <li>Im Browser bei Google anmelden und erlauben.</li>
                                    <li>Den Text zwischen <code>---&gt;</code> und <code>&lt;---</code> hier einfügen.</li>
                                </ol>
                                <form method="POST" action="{{ route('admin.credentials.gdrive') }}" class="tm-stack" autocomplete="off">@csrf
                                    <div>
                                        <label class="tm-label" for="token">Token</label>
                                        <textarea class="tm-input" id="token" name="token" rows="3" style="height:auto;padding:10px 12px;font-family:ui-monospace,monospace;font-size:12px" placeholder='{"access_token":"...","refresh_token":"..."}'></textarea>
                                        @error('token')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                                    </div>
                                    <div>
                                        <label class="tm-label" for="folder">Ordner in Google Drive</label>
                                        <input class="tm-input" id="folder" name="folder" value="{{ old('folder', $gdriveFolder) }}" style="max-width:320px">
                                        @error('folder')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                                    </div>
                                    <div><button class="tm-btn tm-btn-primary">Google Drive verbinden</button></div>
                                </form>
                            </details>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach
        </div>
    </div>
</x-app-layout>
