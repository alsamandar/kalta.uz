<x-app-layout :title="__('Access denied')" :noindex="true" :ads="false">
    <div class="container error-page">
        <div class="error-code">403</div>
        <h1>{{ __('Access denied') }}</h1>
        <p>{{ __('You do not have access to this page.') }}</p>
        <p><a class="btn btn-primary btn-lg" href="{{ lroute('home') }}"><x-i name="arrow-left" :size="20" />{{ __('Back to the home page') }}</a></p>
    </div>
</x-app-layout>
