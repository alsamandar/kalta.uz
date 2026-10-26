<section>
    <h2>{{ __('Profile Information') }}</h2>
    <p class="muted">{{ __("Update your account's profile information and email address.") }}</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ route('profile.update') }}" style="margin-top: 20px">
        @csrf
        @method('patch')

        <div class="field">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="help">
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" class="btn btn-ghost btn-sm" type="submit">{{ __('Click here to re-send the verification email.') }}</button>
                </p>
            @endif
        </div>

        <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
    </form>
</section>
