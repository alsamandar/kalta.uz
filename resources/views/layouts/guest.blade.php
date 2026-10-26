<x-app-layout :title="$title" :noindex="true" :ads="false">
    <div class="auth">
        <div class="auth-card card">
            @if ($heading)
                <h1>{{ $heading }}</h1>
            @endif
            @if ($lead)
                <p class="lead">{{ $lead }}</p>
            @endif
            {{ $slot }}
        </div>
    </div>
</x-app-layout>
