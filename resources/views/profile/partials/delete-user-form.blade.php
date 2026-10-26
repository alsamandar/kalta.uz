<section>
    <h2>{{ __('Delete Account') }}</h2>
    <p class="muted">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}</p>

    <details @if ($errors->userDeletion->isNotEmpty()) open @endif style="margin-top: 16px">
        <summary class="btn btn-danger" style="display:inline-flex;list-style:none">{{ __('Delete Account') }}</summary>

        <form method="post" action="{{ route('profile.destroy') }}" style="margin-top: 20px">
            @csrf
            @method('delete')

            <p><strong>{{ __('Are you sure you want to delete your account?') }}</strong> {{ __('Please enter your password to confirm.') }}</p>

            <div class="field">
                <x-input-label for="delete_password" :value="__('Password')" class="sr-only" />
                <x-text-input id="delete_password" name="password" type="password" :placeholder="__('Password')" autocomplete="current-password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <button class="btn btn-danger" type="submit">{{ __('Delete Account') }}</button>
        </form>
    </details>
</section>
