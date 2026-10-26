<x-app-layout :title="__('Page not found')" :noindex="true" :ads="false">
    <div class="container error-page">
        <div class="error-code">404</div>
        <h1>{{ __('Page not found') }}</h1>
        <p>{{ __('The page you are looking for does not exist or the link is no longer valid.') }}</p>
        <p><a class="btn btn-primary btn-lg" href="{{ lroute('home') }}"><x-i name="arrow-left" :size="20" />{{ __('Back to the home page') }}</a></p>
    </div>
</x-app-layout>
