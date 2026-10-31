@props(['status'])
@if ($status)
    <div {{ $attributes->merge(['class' => 'tm-alert ok']) }}>{{ $status }}</div>
@endif
