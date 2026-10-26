<?php

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public string $pageTitle;

    public string $metaDescription;

    public string $canonicalUrl;

    public string $ogImage;

    public string $robots;

    /** @var array<string, string> locale => URL of this page in that language */
    public array $alternateUrls = [];

    /**
     * @param  bool  $alternates  emit hreflang links (only for pages that exist in every language)
     * @param  bool  $minimal  standalone pages (link-in-bio, invitations): no site header, footer or ads
     * @param  bool  $trail  emit BreadcrumbList data (Home > this page) to match the visible breadcrumbs
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs  extra steps between Home and this page
     * @param  array<int, array<string, mixed>>  $jsonld  extra schema.org blocks
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $noindex = false,
        ?string $canonical = null,
        ?string $image = null,
        public string $ogType = 'website',
        public bool $alternates = false,
        public string $bodyClass = '',
        public bool $minimal = false,
        public bool $ads = true,
        public array $breadcrumbs = [],
        public bool $trail = false,
        public array $jsonld = [],
        public bool $indexable = false,
    ) {
        $brand = config('kalta.name');

        $this->pageTitle = $title
            ? (Str::contains($title, 'Kalta') ? $title : "$title · $brand")
            : $brand . ' — ' . __('Free URL Shortener, QR Codes & File Sharing');

        $this->metaDescription = $description
            ?: __('Shorten long links, create QR codes, share files and text snippets, build a link-in-bio page or a wedding invitation. Free, no sign-up.');

        $this->canonicalUrl = $canonical ?: url()->current();
        $this->ogImage = $image ?: asset_v('img/og-' . app()->getLocale() . '.png');
        $this->robots = ($noindex || !$indexable || !config('kalta.seo_index'))
            ? 'noindex, nofollow'
            : 'index, follow, max-image-preview:large, max-snippet:-1';

        // The language switcher needs the page's URL in every language, whether or not hreflang is emitted
        $this->alternateUrls = locale_alternates();
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
