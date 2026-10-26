@php
    use App\Models\Bio;

    $description = $kalta?->description;
    $avatar = $bio->avatarUrl();
@endphp
<x-app-layout
    :title="$bio->title"
    :description="$description ?: __(':name — all my links in one place', ['name' => $bio->title])"
    :image="$avatar"
    ogType="profile"
    :indexable="true"
    :minimal="true">

    <div class="bio-body" data-bio-theme="{{ $bio->theme() }}">
        <div class="bio">
            <div class="bio-avatar">
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="{{ $bio->title }}" width="116" height="116" decoding="async">
                @else
                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr($bio->title, 0, 1)) }}</span>
                @endif
            </div>

            <h1>{{ $bio->title }}</h1>
            @if ($description)
                <p class="bio-desc">{{ $description }}</p>
            @endif

            <ul class="bio-links">
                @foreach ($bio->links() as $link)
                    <li>
                        <a class="bio-link" href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow ugc">
                            <x-i :name="$link['key']" :size="22" /><span>{{ Bio::label($link['key']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <p class="bio-foot">
                {{ __('Made with') }} <a href="{{ lroute('home') }}">Kalta.uz</a> ·
                <a href="{{ lroute('bio.create') }}">{{ __('Create your own page') }}</a>
            </p>
        </div>
    </div>
</x-app-layout>
