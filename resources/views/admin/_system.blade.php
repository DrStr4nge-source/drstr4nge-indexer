@php
    use App\Support\Format;
    $bar = function (?float $p) {
        if ($p === null) return '';
        $color = $p >= 90 ? 'var(--tm-bad)' : ($p >= 75 ? 'var(--tm-warn)' : 'var(--tm-accent)');
        return '<div class="tm-bar" style="margin-top:10px"><span style="width:' . $p . '%;background:' . $color . '"></span></div>';
    };
    $na = '<div class="v tm-muted">–</div><div class="tm-muted tm-small">nicht verfügbar</div>';
    $up = $system['uptime'];
    $upText = $up === null ? null : ($up >= 86400 ? intdiv($up, 86400) . ' Tage ' : '') . intdiv($up % 86400, 3600) . ' Std ' . intdiv($up % 3600, 60) . ' Min';
@endphp
<div class="tm-grid cols-4">
    <div class="tm-stat">
        <div class="tm-muted tm-small">CPU</div>
        @if ($system['cpu'] !== null)
            <div class="v">{{ number_format($system['cpu'], 1, ',', '.') }} %</div>
            <div class="tm-muted tm-small">{{ $system['cores'] }} {{ $system['cores'] == 1 ? 'Kern' : 'Kerne' }}@if ($system['load']) · Last {{ number_format($system['load'][0], 2, ',', '.') }} / {{ number_format($system['load'][1], 2, ',', '.') }} / {{ number_format($system['load'][2], 2, ',', '.') }}@endif</div>
            {!! $bar($system['cpu']) !!}
        @else {!! $na !!} @endif
    </div>
    <div class="tm-stat">
        <div class="tm-muted tm-small">RAM</div>
        @if ($system['ram'])
            <div class="v">{{ number_format($system['ram']['percent'], 0, ',', '.') }} %</div>
            <div class="tm-muted tm-small">{{ Format::bytes($system['ram']['used']) }} von {{ Format::bytes($system['ram']['total']) }}@if ($system['swap']) · Swap {{ Format::bytes($system['swap']['used']) }}@endif</div>
            {!! $bar($system['ram']['percent']) !!}
        @else {!! $na !!} @endif
    </div>
    <div class="tm-stat">
        <div class="tm-muted tm-small">Speicherplatz</div>
        @if ($system['disk'])
            <div class="v">{{ number_format($system['disk']['percent'], 0, ',', '.') }} %</div>
            <div class="tm-muted tm-small">{{ Format::bytes($system['disk']['total'] - $system['disk']['used']) }} frei von {{ Format::bytes($system['disk']['total']) }}</div>
            {!! $bar($system['disk']['percent']) !!}
        @else {!! $na !!} @endif
    </div>
    <div class="tm-stat">
        <div class="tm-muted tm-small">Laufzeit</div>
        @if ($upText)
            <div class="v" style="font-size:20px;padding:4px 0">{{ $upText }}</div>
            <div class="tm-muted tm-small">aktualisiert {{ now()->format('H:i:s') }}</div>
        @else {!! $na !!} @endif
    </div>
</div>
