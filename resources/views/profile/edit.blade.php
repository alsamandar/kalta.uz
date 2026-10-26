<x-app-layout :title="__('Profile')" :noindex="true" :ads="false">
    <x-page-hero :title="__('Profile')" icon="user" />

    <div class="container narrow page-body">
        <div class="card">@include('profile.partials.update-profile-information-form')</div>
        <div class="card">@include('profile.partials.update-password-form')</div>
        <div class="card danger-zone">@include('profile.partials.delete-user-form')</div>
    </div>
</x-app-layout>
