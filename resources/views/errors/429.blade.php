<x-app-layout :title="__('Too many requests')" :noindex="true" :ads="false">
    <div class="container error-page">
        <div class="error-code">429</div>
        <h1>{{ __('Too many requests') }}</h1>
        <p>{{ __('Please wait a minute and try again.') }}</p>
        <p><a class="btn btn-primary btn-lg" href="{{ lroute('home') }}"><x-i name="arrow-left" :size="20" />{{ __('Back to the home page') }}</a></p>
    </div>
</x-app-layout>
