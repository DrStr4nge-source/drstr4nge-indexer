<x-app-layout>
    <x-slot name="title">Admin: Einstellungen</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Einstellungen</h1></x-slot>
    <div>
        @include('admin._nav')
        @unless (\App\Support\Brand::storageLinked())
            <div class="tm-alert bad" style="margin-bottom:16px;max-width:720px">
                <span>Hochgeladene Bilder (Logo, Cover) können nicht angezeigt werden: Die Verknüpfung <code>public/storage</code> fehlt.
                Auf dem Server ausführen: <code>cd {{ base_path() }} &amp;&amp; php artisan storage:link</code></span>
            </div>
        @endunless
        <section class="tm-panel" style="max-width:720px">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="tm-stack">@csrf
                <div>
                    <label class="tm-label" for="site_name">Name der Seite</label>
                    <input class="tm-input" id="site_name" name="site_name" value="{{ old('site_name', $siteName) }}" maxlength="60" required>
                    <p class="tm-muted tm-small" style="margin-top:6px">Erscheint im Browser-Tab und als Text-Logo, falls kein Logo hochgeladen ist. Das letzte Wort wird orange.</p>
                    @error('site_name')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>

                <div>
                    <label class="tm-label" for="logo">Logo</label>
                    @if ($logoPath)
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:10px;padding:12px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-bg-2)">
                            <img src="{{ asset('storage/' . $logoPath) }}" alt="Aktuelles Logo" style="height:40px;width:auto;max-width:220px;object-fit:contain">
                            <label class="tm-small tm-muted" style="display:flex;align-items:center;gap:8px;margin-left:auto">
                                <input type="checkbox" name="remove_logo" value="1"> Logo entfernen
                            </label>
                        </div>
                    @endif
                    <input class="tm-input" id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                    <p class="tm-muted tm-small" style="margin-top:6px">PNG, JPG oder WebP, höchstens 1 MB. Am besten quer und mit transparentem Hintergrund, etwa 300 x 64 Pixel. Wird oben links, beim Login und als Browser-Symbol gezeigt.</p>
                    @error('logo')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>

                <div>
                    <label class="tm-label" for="registration_mode">Registrierung neuer User</label>
                    <select class="tm-select" id="registration_mode" name="registration_mode">
                        @foreach (\App\Support\Registration::MODES as $k => $label)
                            <option value="{{ $k }}" @selected(old('registration_mode', $registrationMode) === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="tm-muted tm-small" style="margin-top:6px">Codes erzeugst du unter <a class="tm-link" href="{{ route('admin.invites') }}">Einladungen</a>. Bestehende User sind nicht betroffen.</p>
                </div>

                <div>
                    <label class="tm-small" style="display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="admin_require_2fa" value="1" @checked(old('admin_require_2fa', $require2fa))> Zwei-Faktor-Anmeldung für alle Admins verlangen
                    </label>
                    <p class="tm-muted tm-small" style="margin-top:6px">Admins ohne Zwei-Faktor müssen es vor dem nächsten Klick im Admin-Bereich einrichten. <a class="tm-link" href="{{ route('admin.2fa') }}">Zwei-Faktor einrichten</a></p>
                    @error('admin_require_2fa')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>

                <div><button class="tm-btn tm-btn-primary">Speichern</button></div>
            </form>
        </section>
    </div>
</x-app-layout>
