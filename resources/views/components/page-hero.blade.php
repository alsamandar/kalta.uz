@props(['title', 'lead' => null])
{{-- just the heading and one line; the breadcrumb trail is only in the structured data --}}
<section class="page-hero">
    <div class="container">
        <h1>{{ $title }}</h1>
        @if ($lead)
            <p class="lead">{{ $lead }}</p>
        @endif
    </div>
</section>
