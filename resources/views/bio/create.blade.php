@php
    use App\Models\Bio;

    $swatches = [
        'default' => 'linear-gradient(160deg,#fff4e6,#ffdcb4)',
        'sunset' => 'linear-gradient(160deg,#ff9a5a,#ff4d6d 55%,#7b2ff7)',
        'ocean' => 'linear-gradient(160deg,#0f4c81,#1d8fe1 60%,#5ccfff)',
        'forest' => 'linear-gradient(160deg,#0b3d2e,#1f7a55 60%,#8bd35a)',
        'night' => 'linear-gradient(160deg,#0b0b12,#1a1a2e 55%,#302b63)',
    ];
    $themeNames = [
        'default' => __('Sand'), 'sunset' => __('Sunset'), 'ocean' => __('Ocean'), 'forest' => __('Forest'), 'night' => __('Night'),
    ];
    $placeholders = ['instagram' => '@username', 'telegram' => '@username', 'twitter' => '@username', 'github' => '@username', 'blog' => 'https://your-site.uz'];
@endphp
<x-app-layout
    :title="__('Link in bio — one page for all your links')"
    :description="__('Create a free link-in-bio page for Instagram or Telegram: your photo, short description and all your links on one page with a single short address.')"
    :alternates="true"
    :trail="true">

    <x-page-hero :title="__('Link in bio')" :lead="__('One page for all your links. Perfect for your Instagram or Telegram profile.')" />

    <div class="container page-body">
        <form class="bio-form-layout" method="POST" action="{{ lroute('bio.store') }}" enctype="multipart/form-data" data-bio-form data-resilient>
            @csrf
            <x-submission />

            <div class="card">
                <div class="field">
                    <span class="label">{{ __('Photo') }} <span class="opt">({{ __('optional') }})</span></span>
                    <div class="avatar-field">
                        <span class="avatar-thumb" data-avatar-thumb><x-i name="image" :size="28" /></span>
                        <input class="input" type="file" name="avatar_icon" id="avatar_icon" accept=".jpg,.jpeg,.png,.webp,image/*" data-shrink="1200" aria-label="{{ __('Photo') }}"
                            @if ($errors->has('avatar_icon')) aria-invalid="true" @endif>
                    </div>
                    @error('avatar_icon')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label class="label" for="title">{{ __('Your name or title') }}</label>
                    <input class="input" type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="80" autocomplete="name"
                        @if ($errors->has('title')) aria-invalid="true" @endif>
                    @error('title')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <label class="label" for="description">{{ __('About you') }} <span class="opt">({{ __('optional') }})</span></label>
                    <textarea class="textarea" id="description" name="description" rows="3" maxlength="255">{{ old('description') }}</textarea>
                    @error('description')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
                </div>

                <fieldset style="border:0;padding:0;margin:0">
                    <legend class="label" style="margin-bottom:10px">{{ __('Your links') }}</legend>
                    @foreach (Bio::LINKS as $key)
                        <div class="field">
                            <label class="sr-only" for="{{ $key }}">{{ Bio::label($key) }}</label>
                            <div class="input-icon">
                                <x-i :name="$key" :size="20" />
                                <input class="input" type="text" inputmode="url" id="{{ $key }}" name="{{ $key }}" value="{{ old($key) }}" maxlength="255"
                                    autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="{{ Bio::label($key) }} — {{ $placeholders[$key] }}"
                                    @if ($errors->has($key)) aria-invalid="true" @endif>
                            </div>
                            @error($key)<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <p class="help" style="margin-top:-8px">{{ __('Type @username or paste a full link — we take care of the rest.') }}</p>
                </fieldset>

                <fieldset style="border:0;padding:0;margin:22px 0 0">
                    <legend class="label" style="margin-bottom:10px">{{ __('Colour theme') }}</legend>
                    <div class="theme-picker">
                        @foreach (Bio::THEMES as $theme)
                            <label class="theme-option" title="{{ $themeNames[$theme] }}">
                                <input type="radio" name="style" value="{{ $theme }}" @checked(old('style', 'default') === $theme)>
                                <span style="--swatch: {{ $swatches[$theme] }}"></span>
                                <span class="sr-only">{{ $themeNames[$theme] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="form-actions" style="margin-top:26px">
                    <button type="submit" class="btn btn-primary btn-lg" data-loading="{{ __('Creating…') }}"><x-i name="link" :size="20" /><span>{{ __('Create my page') }}</span></button>
                </div>
            </div>

            <aside class="sticky" aria-label="{{ __('Preview') }}">
                <p class="eyebrow-label" style="margin-bottom:10px">{{ __('Preview') }}</p>
                <div class="bio-preview" data-bio-preview data-bio-theme="{{ old('style', 'default') }}">
                    <div class="bio">
                        <div class="bio-avatar" data-prev="avatar">•</div>
                        <h2 data-prev="title">{{ __('Your name') }}</h2>
                        <p class="bio-desc" data-prev="description" hidden></p>
                        <ul class="bio-links">
                            @foreach (Bio::LINKS as $key)
                                <li data-prev-link="{{ $key }}" hidden>
                                    <span class="bio-link"><x-i :name="$key" :size="20" /><span>{{ Bio::label($key) }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>
        </form>

        <article class="prose" style="margin-top: 36px">
            <h2>{{ __('Why use a link-in-bio page?') }}</h2>
            <p>{{ __('Social networks allow one link in your profile. Put Instagram, Telegram, X, GitHub and your website on one page and share a single short address.') }}</p>
        </article>
    </div>
</x-app-layout>
