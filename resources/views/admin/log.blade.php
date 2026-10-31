<x-app-layout>
    <x-slot name="title">Admin: Log</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Log</h1></x-slot>
    <div>
        @include('admin._nav')
        @if ($problems)
            <div class="tm-alert bad" style="display:block;margin-bottom:16px">
                <div style="font-weight:600;margin-bottom:6px">Aktuelle Probleme (Überwachung)</div>
                @foreach ($problems as $p)<div class="tm-small" style="margin-top:4px">{{ $p }}</div>@endforeach
            </div>
        @endif
        <section class="tm-panel">
            <div class="tm-form-row" style="gap:8px;flex-wrap:wrap;justify-content:space-between;margin-bottom:14px">
                <div class="tm-tabs" style="margin:0">
                    @foreach (['probleme' => 'Warnungen und Fehler', 'fehler' => 'Nur Fehler', 'alle' => 'Alles'] as $k => $label)
                        <a href="{{ route('admin.log', ['stufe' => $k, 'q' => $q ?: null]) }}" @class(['is-active' => $filter === $k])>{{ $label }}</a>
                    @endforeach
                </div>
                <form method="get" class="tm-form-row" style="gap:8px">
                    <input type="hidden" name="stufe" value="{{ $filter }}">
                    <input class="tm-input" type="search" name="q" value="{{ $q }}" placeholder="Suchen" style="width:200px;height:34px">
                </form>
            </div>
            <div class="tm-list">
                @forelse ($entries as $e)
                    @php($lvl = \App\Support\LogReader::LEVELS[$e['level']] ?? 0)
                    <details style="display:block;padding:9px 0;border-bottom:1px solid var(--tm-line)">
                        <summary style="cursor:pointer;display:flex;gap:10px;align-items:baseline;list-style:none">
                            <span class="tm-muted tm-small nowrap" style="min-width:118px">{{ \App\Support\Format::dt($e['time'], 'd.m. H:i:s') }}</span>
                            <span class="tm-chip {{ $lvl >= 4 ? 'bad' : ($lvl === 3 ? 'accent' : '') }}" style="min-width:64px;text-align:center">{{ ['WARNING' => 'Warnung', 'ERROR' => 'Fehler', 'CRITICAL' => 'Kritisch', 'ALERT' => 'Alarm', 'EMERGENCY' => 'Notfall', 'INFO' => 'Info', 'NOTICE' => 'Hinweis', 'DEBUG' => 'Debug'][$e['level']] ?? $e['level'] }}</span>
                            <span class="tm-small" style="word-break:break-word">{{ \Illuminate\Support\Str::limit($e['message'], 300) }}</span>
                        </summary>
                        @if ($e['details'] !== '' || mb_strlen($e['message']) > 300)
                            <pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;font-size:11.5px;max-height:360px;overflow:auto;background:var(--tm-bg);border:1px solid var(--tm-line);border-radius:8px;padding:10px;color:var(--tm-muted)">{{ $e['message'] }}

{{ \Illuminate\Support\Str::limit($e['details'], 20000) }}</pre>
                        @endif
                    </details>
                @empty
                    <div class="tm-empty" style="margin:8px 0">{{ $filter === 'alle' ? 'Das Log ist leer.' : 'Keine Warnungen oder Fehler.' }}</div>
                @endforelse
            </div>
            <div class="tm-form-row" style="justify-content:space-between;gap:8px;margin-top:14px;flex-wrap:wrap">
                <span class="tm-muted tm-small">Log-Dateien: {{ \App\Support\Format::bytes($size) }} · neueste 300 Einträge</span>
                <form method="POST" action="{{ route('admin.log.clear') }}" onsubmit="return confirm('Log wirklich leeren?')">@csrf<button class="tm-btn tm-btn-sm">Log leeren</button></form>
            </div>
        </section>
    </div>
</x-app-layout>
