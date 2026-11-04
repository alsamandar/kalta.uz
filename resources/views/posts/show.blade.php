@php
    use App\Support\PostPreview;

    $short = $kalta->short_url;
    $photo = $post->imageUrl();
    $image = $photo ?: (PostPreview::available() ? PostPreview::url($post) : null);
    $description = $post->excerpt ?: $post->title;
    $published = session('post-published');

    $jsonld = [array_filter([
        '@type' => 'BlogPosting',
        '@id' => $short . '#post',
        'headline' => \Illuminate\Support\Str::limit($post->title, 110, '…'),
        'description' => $description,
        'image' => $image ? [$image] : null,
        'datePublished' => $post->created_at->toIso8601String(),
        'dateModified' => $post->updated_at->toIso8601String(),
        'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author] : ['@id' => url('/') . '#organization'],
        'publisher' => ['@id' => url('/') . '#organization'],
        'mainEntityOfPage' => $short,
        'wordCount' => $post->words,
    ])];
@endphp
<x-app-layout
    :title="$post->title"
    :description="$description"
    :image="$image"
    :imageSize="$photo ? $post->imageSize() : null"
    ogType="article"
    :canonical="$short"
    :indexable="$post->indexable()"
    :jsonld="$jsonld"
    :minimal="true">

    @push('head')
        <link rel="stylesheet" href="{{ asset_v('css/post.css') }}">
        <meta property="article:published_time" content="{{ $post->created_at->toIso8601String() }}">
        <meta property="article:modified_time" content="{{ $post->updated_at->toIso8601String() }}">
        @if ($post->author)<meta property="article:author" content="{{ $post->author }}"><meta name="author" content="{{ $post->author }}">@endif
    @endpush

    <div class="post-page" @if ($published) data-post-published @endif>
        <header class="post-bar">
            <a class="post-brand" href="{{ lroute('home') }}"><x-logo :size="26" /><span>Kalta<b>UZ</b></span></a>
            <div class="post-bar-actions">
                @if ($canEdit)
                    <a class="btn btn-secondary btn-sm" href="{{ route('posts.edit', $post) }}"><x-i name="edit" :size="16" />{{ __('Edit') }}</a>
                @endif
                <a class="btn btn-ghost btn-sm" href="{{ lroute('posts.create') }}"><x-i name="plus" :size="16" />{{ __('New post') }}</a>
            </div>
        </header>

        @if (session('success'))
            <div class="post-flash" role="status"><x-i name="check" :size="18" /><span>{{ session('success') }}</span></div>
        @endif

        <article class="post">
            <h1>{{ $post->title }}</h1>
            <p class="post-meta">
                @if ($post->author)<span class="post-author">{{ $post->author }}</span><span aria-hidden="true">·</span>@endif
                <time datetime="{{ $post->created_at->toDateString() }}">{{ $post->created_at->translatedFormat('j F Y') }}</time>
                <span aria-hidden="true">·</span>
                <span>{{ __(':count min read', ['count' => $post->readingMinutes()]) }}</span>
            </p>

            <div class="post-body">{!! $post->body !!}</div>
        </article>

        <footer class="post-foot" data-copy-scope>
            <div class="share-row">
                <button type="button" class="btn btn-secondary btn-sm" data-copy-text="{{ $short }}"><x-i name="copy" :size="16" />{{ __('Copy link') }}</button>
                <button type="button" class="btn btn-secondary btn-sm" data-share data-share-url="{{ $short }}" hidden><x-i name="share" :size="16" />{{ __('Share') }}</button>
                <a class="btn btn-secondary btn-sm" href="https://t.me/share/url?{{ http_build_query(['url' => $short, 'text' => $post->title], '', '&', PHP_QUERY_RFC3986) }}" target="_blank" rel="noopener"><x-i name="telegram" :size="16" />Telegram</a>
                <a class="btn btn-secondary btn-sm" href="https://wa.me/?text={{ rawurlencode($post->title . "\n" . $short) }}" target="_blank" rel="noopener"><x-i name="whatsapp" :size="16" />WhatsApp</a>
            </div>
            <p>
                {{ __('Made with') }} <a href="{{ lroute('home') }}">Kalta.uz</a> ·
                <a href="{{ lroute('posts.create') }}">{{ __('Write your own post') }}</a>
            </p>
        </footer>
    </div>
</x-app-layout>
