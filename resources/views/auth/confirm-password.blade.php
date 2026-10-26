<x-guest-layout :title="__('Confirm Password')" :heading="__('Confirm your password')"
    :lead="__('This is a secure area. Please confirm your password before continuing.')">
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <button class="btn btn-primary btn-lg btn-block" type="submit">{{ __('Confirm') }}</button>
    </form>
</x-guest-layout>
