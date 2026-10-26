{{-- Drawings for the icons that are repeated for every guest; used through <x-iu name="..."/> --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    @foreach ($icons as $name)
        <symbol id="i-{{ $name }}" viewBox="0 0 24 24">{!! \App\Support\Icons::svg($name) !!}</symbol>
    @endforeach
</svg>
