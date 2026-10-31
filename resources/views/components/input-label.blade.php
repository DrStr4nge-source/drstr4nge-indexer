@props(['value'])
<label {{ $attributes->merge(['class' => 'tm-label']) }}>{{ $value ?? $slot }}</label>
