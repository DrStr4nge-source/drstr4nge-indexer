<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:10px">Zwei-Faktor-Code</h1>
    <p class="tm-muted tm-small" style="margin-bottom:18px">Gib den 6-stelligen Code aus deiner App ein. Kein Handy zur Hand? Ein Wiederherstellungscode geht auch.</p>
    <form method="POST" action="{{ route('admin.2fa.challenge.verify') }}" class="tm-stack" autocomplete="off">@csrf
        <div>
            <label class="tm-label" for="code">Code</label>
            <input class="tm-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required style="font-size:18px;letter-spacing:2px">
            @error('code')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Weiter</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:14px;text-align:center">@csrf
        <button class="tm-small tm-muted" style="background:none;border:0;cursor:pointer;text-decoration:underline">Abmelden</button>
    </form>
</x-guest-layout>
