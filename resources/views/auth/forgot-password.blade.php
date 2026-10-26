<x-guest-layout :title="__('Forgot your password?')" :heading="__('Forgot your password?')"
    :lead="__('No problem. Enter your e-mail and we will send you a link to choose a new one.')">
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <button class="btn btn-primary btn-lg btn-block" type="submit">{{ __('Email Password Reset Link') }}</button>

        <div class="auth-foot">
            <a href="{{ route('login') }}">{{ __('Back to log in') }}</a>
        </div>
    </form>
</x-guest-layout>
