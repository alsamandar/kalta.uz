<x-app-layout :title="__('This link has expired')" :noindex="true" :ads="false">
    <div class="container error-page">
        <div class="error-code">410</div>
        <h1>{{ __('This link has expired') }}</h1>
        <p>{{ __('The content behind this link was removed or has expired.') }}</p>
        <p><a class="btn btn-primary btn-lg" href="{{ lroute('home') }}"><x-i name="arrow-left" :size="20" />{{ __('Back to the home page') }}</a></p>
    </div>
</x-app-layout>
