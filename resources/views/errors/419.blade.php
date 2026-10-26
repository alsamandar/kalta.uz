<x-app-layout :title="__('Page expired')" :noindex="true" :ads="false">
    <div class="container error-page">
        <div class="error-code">419</div>
        <h1>{{ __('Page expired') }}</h1>
        <p>{{ __('Please go back, refresh the page and try again.') }}</p>
        <p><a class="btn btn-primary btn-lg" href="{{ lroute('home') }}"><x-i name="arrow-left" :size="20" />{{ __('Back to the home page') }}</a></p>
    </div>
</x-app-layout>
