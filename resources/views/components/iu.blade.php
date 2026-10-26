@props(['name', 'size' => 20])
{{-- An icon from the page's inline sprite (see invitations/_sprite): a few bytes per use instead of the whole drawing, for lists --}}
<svg {{ $attributes->merge(['class' => 'icon icon-u']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#i-{{ $name }}"/></svg>
