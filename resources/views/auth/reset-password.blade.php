<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:18px">Neues Passwort</h1>
    <form method="POST" action="{{ route('password.store') }}" class="tm-stack">@csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label class="tm-label" for="email">E-Mail</label>
            <input class="tm-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username">
            @error('email')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <div>
            <label class="tm-label" for="password">Neues Passwort</label>
            <input class="tm-input" id="password" type="password" name="password" required autofocus autocomplete="new-password">
            @error('password')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <div>
            <label class="tm-label" for="password_confirmation">Passwort wiederholen</label>
            <input class="tm-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            @error('password_confirmation')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Passwort speichern</button>
    </form>
</x-guest-layout>
