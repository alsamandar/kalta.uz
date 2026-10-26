@props(['title', 'lead' => null, 'icon' => null])
<section class="page-hero">
    <div class="container">
        <nav aria-label="{{ __('Breadcrumb') }}">
            <ol class="breadcrumbs">
                <li><a href="{{ lroute('home') }}">{{ __('Home') }}</a></li>
                <li aria-current="page">{{ $title }}</li>
            </ol>
        </nav>
        <h1>
            @if ($icon)<span class="tool-icon"><x-i :name="$icon" :size="26" /></span>@endif
            <span>{{ $title }}</span>
        </h1>
        @if ($lead)
            <p class="lead">{{ $lead }}</p>
        @endif
    </div>
</section>
