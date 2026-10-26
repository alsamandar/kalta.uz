<x-guest-layout :title="__('Log in')" :heading="__('Welcome back')" :lead="__('Log in to see and manage your links.')">
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label class="check"><input type="checkbox" name="remember" id="remember_me"> <span>{{ __('Remember me') }}</span></label>

        <button class="btn btn-primary btn-lg btn-block" type="submit" style="margin-top: 12px">{{ __('Log in') }}</button>

        <div class="auth-foot">
            <a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
        </div>
    </form>
</x-guest-layout>
