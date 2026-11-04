@php
    // one page for writing a new post and for editing one; without JavaScript the body is a plain textarea
    $editing = $post !== null;
    $format = old('format', $editing ? 'html' : 'text');
    $body = old('body', $editing ? $post->body : '');
@endphp
<x-app-layout
    :title="$editing ? __('Edit post') : __('Write a post')"
    :description="__('Publish an article with pictures in a minute, like Telegraph. No account needed; links show a preview in chats.')"
    :alternates="! $editing"
    :noindex="$editing"
    :ads="false">

    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/post.css') }}">
    @endpush

    <div class="container post-write">
        <form class="post-editor" method="POST" action="{{ $editing ? route('posts.update', $post) : lroute('posts.store') }}"
            data-resilient data-post-form @unless ($editing) data-post-draft @endunless>
            @csrf
            @if ($editing) @method('PATCH') @endif
            <x-submission />
            <input type="hidden" name="format" value="{{ $format }}" data-post-format>

            <div class="field pe-field">
                <label class="sr-only" for="title">{{ __('Title') }}</label>
                <textarea class="pe-title" id="title" name="title" rows="1" required maxlength="200" autocomplete="off"
                    placeholder="{{ __('Title') }}"
                    @if ($errors->has('title')) aria-invalid="true" @endif>{{ old('title', $post?->title) }}</textarea>
                @error('title')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
            </div>

            <div class="field pe-field">
                <label class="sr-only" for="author">{{ __('Your name') }}</label>
                <input class="pe-author" id="author" name="author" type="text" maxlength="100" autocomplete="name"
                    placeholder="{{ __('Your name (optional)') }}" value="{{ old('author', $post?->author ?? auth()->user()?->name) }}">
            </div>

            <div class="field pe-field">
                <label class="sr-only" for="body">{{ __('Your story') }}</label>
                <textarea class="textarea pe-text" id="body" name="body" rows="14" maxlength="400000" data-post-body
                    data-image-upload="{{ route('posts.images') }}" data-placeholder="{{ __('Your story…') }}"
                    placeholder="{{ __('Your story… Leave an empty line between paragraphs.') }}"
                    @if ($errors->has('body')) aria-invalid="true" @endif>{{ $body }}</textarea>
                @error('body')<p class="field-error" role="alert"><x-i name="alert" :size="18" />{{ $message }}</p>@enderror
            </div>

            <div class="pe-actions">
                <button type="submit" class="btn btn-primary btn-lg" data-loading="{{ $editing ? __('Saving…') : __('Publishing…') }}">
                    <x-i name="send" :size="20" /><span>{{ $editing ? __('Save') : __('Publish') }}</span>
                </button>
                @if ($editing)
                    <a class="btn btn-ghost" href="{{ $post->kalta->short_url }}">{{ __('Cancel') }}</a>
                @endif
                <p class="pe-hint muted" data-post-status>
                    @if ($editing)
                        {{ __('Changes are visible as soon as you save.') }}
                    @else
                        {{ __('No account needed. Only this browser (and your account, if you are logged in) can edit the post later.') }}
                    @endif
                </p>
            </div>
        </form>

        @if ($editing)
            <form class="pe-delete" method="POST" action="{{ route('posts.destroy', $post) }}" data-confirm="{{ __('Delete this post? This cannot be undone.') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm"><x-i name="trash" :size="16" />{{ __('Delete post') }}</button>
            </form>
        @else
            <article class="prose pe-about">
                <h2>{{ __('Write and publish a post') }}</h2>
                <p>{{ __('Give it a title, write your text, add pictures and tap “Publish”. You get a short, readable address such as kalta.uz/my-story-11-04.') }}</p>
                <p>{{ __('Links to your post show the title, the first words and a picture in Telegram, WhatsApp, Facebook and other apps. Longer posts can also be found through search engines.') }}</p>
            </article>
        @endif
    </div>
</x-app-layout>
