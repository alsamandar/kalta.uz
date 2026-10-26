<x-guest-layout :title="__('Verify your e-mail')" :heading="__('Verify your e-mail')"
    :lead="__('Thanks for signing up! Please confirm your e-mail address using the link we just sent you. If it did not arrive, we will gladly send another.')">
    <div class="row-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-primary" type="submit">{{ __('Resend Verification Email') }}</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-ghost" type="submit">{{ __('Log Out') }}</button>
        </form>
    </div>
</x-guest-layout>
