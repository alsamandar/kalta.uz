@props(['size' => 36])
<svg {{ $attributes->merge(['class' => 'logo-mark']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
    <rect width="32" height="32" rx="9" fill="#ff7500"/>
    <g fill="none" stroke="#1b1510" stroke-width="2.3" stroke-linecap="round">
        <circle cx="9.5" cy="10" r="3.3"/>
        <circle cx="9.5" cy="22" r="3.3"/>
        <path d="M12.3 11.8L25.5 22.6M12.3 20.2L25.5 9.4"/>
    </g>
</svg>
