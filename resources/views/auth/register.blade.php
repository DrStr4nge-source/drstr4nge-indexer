<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:4px">Konto erstellen</h1>
    @if ($mode === 'closed')
        <p class="tm-muted" style="margin:10px 0 18px">Die Registrierung ist im Moment geschlossen.</p>
        <a class="tm-btn" href="{{ route('login') }}">Zur Anmeldung</a>
    @else
        <p class="tm-muted tm-small" style="margin-bottom:18px">
            {{ $mode === 'invite' ? 'Registrierung nur mit Einladungscode.' : 'Kostenlos registrieren, VIP kannst du später freischalten.' }}
        </p>
        <form method="POST" action="{{ route('register') }}" class="tm-stack" style="--gap:14px">@csrf
            {{-- Bot-Falle: für Menschen unsichtbar --}}
            <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            @if ($mode === 'invite')
                <div>
                    <label class="tm-label" for="invite">Einladungscode</label>
                    <input class="tm-input" id="invite" name="invite" value="{{ old('invite', $code) }}" placeholder="XXXX-XXXX-XXXX" required autocomplete="off" style="letter-spacing:.08em">
                    @error('invite')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
            @endif
            <div>
                <label class="tm-label" for="name">Benutzername</label>
                <input class="tm-input" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="username">
                @error('name')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            </div>
            <div>
                <label class="tm-label" for="email">E-Mail</label>
                <input class="tm-input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            </div>
            <div>
                <label class="tm-label" for="password">Passwort</label>
                <input class="tm-input" id="password" type="password" name="password" required autocomplete="new-password">
                @error('password')<ul class="tm-error">@foreach ($errors->get('password') as $m)<li>{{ $m }}</li>@endforeach</ul>@enderror
            </div>
            <div>
                <label class="tm-label" for="password_confirmation">Passwort wiederholen</label>
                <input class="tm-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Registrieren</button>
        </form>
        <p class="tm-muted tm-small" style="margin-top:16px;text-align:center">Schon ein Konto? <a class="tm-link" href="{{ route('login') }}">Anmelden</a></p>
    @endif
</x-guest-layout>
