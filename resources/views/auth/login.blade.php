<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:18px">Anmelden</h1>
    @if (session('status'))
        <div class="tm-alert ok" style="margin-bottom:14px">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('login') }}" class="tm-stack">@csrf
        <div>
            <label class="tm-label" for="email">E-Mail</label>
            <input class="tm-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <div>
            <label class="tm-label" for="password">Passwort</label>
            <input class="tm-input" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
            <label class="tm-small tm-muted" style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="remember"> Angemeldet bleiben
            </label>
            @if (Route::has('password.request'))
                <a class="tm-small tm-muted" href="{{ route('password.request') }}">Passwort vergessen?</a>
            @endif
        </div>
        <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Anmelden</button>
    </form>
    @if (\App\Support\Registration::allowed())
        <p class="tm-muted tm-small" style="margin-top:16px;text-align:center">Noch kein Konto? <a class="tm-link" href="{{ route('register') }}">Registrieren</a></p>
    @endif
</x-guest-layout>
