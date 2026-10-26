<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleAndSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_exists_in_every_language(): void
    {
        foreach (['/' => 'uz', '/ru' => 'ru', '/en' => 'en'] as $path => $lang) {
            $this->get($path)->assertOk()->assertSee('<html lang="' . $lang . '"', false);
        }
    }

    public function test_every_localised_page_is_complete_in_every_language(): void
    {
        $pages = ['', '/files/create', '/qr', '/codes/create', '/bio/create', '/invitations/create'];

        foreach (['' => 'uz', '/ru' => 'ru', '/en' => 'en'] as $prefix => $lang) {
            foreach ($pages as $page) {
                $path = $prefix . $page ?: '/';
                $html = $this->get($path)->assertOk()->getContent();

                $this->assertSame(1, substr_count($html, '<h1'), "$path should have exactly one h1");
                $this->assertMatchesRegularExpression('#<title>(.{10,70})</title>#u', $html, "$path title length");
                preg_match('#<meta name="description" content="([^"]+)"#u', $html, $description);
                $this->assertGreaterThan(60, mb_strlen(html_entity_decode($description[1] ?? '')), "$path description too short");
                $this->assertLessThan(200, mb_strlen(html_entity_decode($description[1] ?? '')), "$path description too long");
                $this->assertStringContainsString('<link rel="canonical" href="' . url($path === '/' ? '/' : $path) . '">', $html);
                $this->assertSame(4, substr_count($html, 'rel="alternate" hreflang='), "$path hreflang (uz, ru, en, x-default)");
                $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
                $this->assertStringNotContainsString('Undefined', $html);
            }
        }
    }

    public function test_the_head_carries_open_graph_and_valid_structured_data(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="' . asset('img/og-en.png'), $html);
        $this->assertFileExists(public_path('img/og-en.png'));

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $graph = collect($data['@graph']);

        $this->assertEqualsCanonicalizing(['Organization', 'WebSite', 'WebApplication', 'FAQPage'], $graph->pluck('@type')->all());
        $this->assertCount(6, $graph->firstWhere('@type', 'FAQPage')['mainEntity']);
    }

    public function test_tool_pages_describe_their_breadcrumbs_in_structured_data(): void
    {
        foreach (['/qr' => 'QR kod yaratgich', '/ru/qr' => 'Генератор QR-кодов'] as $path => $name) {
            $html = $this->get($path)->getContent();
            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
            $crumbs = collect(json_decode($match[1], true)['@graph'])->firstWhere('@type', 'BreadcrumbList');

            $this->assertCount(2, $crumbs['itemListElement']);
            $this->assertSame(url($path === '/qr' ? '/' : '/ru'), $crumbs['itemListElement'][0]['item']);
            $this->assertSame($name, $crumbs['itemListElement'][1]['name']);
            $this->assertSame(url($path), $crumbs['itemListElement'][1]['item']);
        }
    }

    public function test_the_url_decides_the_language_not_the_cookie_or_browser(): void
    {
        $this->withUnencryptedCookie('lang', 'ru')->withHeader('Accept-Language', 'en')->get('/qr')->assertSee('<html lang="uz"', false);
        $this->withUnencryptedCookie('lang', 'uz')->get('/ru/qr')->assertSee('<html lang="ru"', false)->assertSee('Генератор QR-кодов');
    }

    public function test_pages_without_a_language_in_the_url_follow_cookie_then_browser(): void
    {
        $this->withHeader('Accept-Language', 'uz-UZ,uz;q=0.9')->get('/login')->assertSee('<html lang="uz"', false);
        $this->withHeader('Accept-Language', 'de-DE')->get('/login')->assertSee('<html lang="uz"', false); // unsupported language: the site default
        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.8')->get('/login')->assertSee('<html lang="ru"', false);
        $this->withHeader('Accept-Language', 'en-US')->get('/login')->assertSee('<html lang="en"', false);
        $this->withUnencryptedCookie('lang', 'en')->withHeader('Accept-Language', 'ru')->get('/login')->assertSee('<html lang="en"', false);
    }

    public function test_visiting_a_language_remembers_it(): void
    {
        $this->get('/ru')->assertCookie('lang', 'ru', false);
        $this->get('/en')->assertCookie('lang', 'en', false);
    }

    public function test_the_language_switch_sets_the_cookie_and_goes_back(): void
    {
        $this->from('/login')->get('/lang/ru')->assertRedirect(url('/login'))->assertCookie('lang', 'ru', false);
        $this->from('https://evil.example/phish')->get('/lang/en')->assertRedirect(url('/'));
        $this->from('http://localhost.evil.example/phish')->get('/lang/en')->assertRedirect(url('/')); // look-alike host
        $this->get('/lang/de')->assertNotFound();
    }

    public function test_the_language_menu_links_to_the_same_page_in_each_language(): void
    {
        $html = $this->get('/ru/qr')->getContent();

        $this->assertStringContainsString('href="' . url('/qr') . '" lang="uz" hreflang="uz"', $html);
        $this->assertStringContainsString('href="' . url('/en/qr') . '" lang="en" hreflang="en"', $html);
    }

    public function test_private_pages_are_kept_out_of_search_results(): void
    {
        $code = $this->postJson('/kaltas/make', ['url' => 'https://example.com'])->json('code');

        $this->get("/created/$code")->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="alternate" hreflang=', false);
        $this->get('/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/nope')->assertNotFound()->assertSee('noindex', false);
    }

    public function test_staging_copies_can_switch_indexing_off(): void
    {
        config(['kalta.seo_index' => false]);

        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');
    }

    public function test_robots_txt_and_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: ' . url('/sitemap.xml'), false)
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /i/')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $bio = \App\Models\Bio::create(['title' => 'Test']);
        $kalta = \App\Models\Kalta::create(['url' => 'BIOT1', 'kaltaable_type' => \App\Models\Bio::class, 'kaltaable_id' => $bio->id, 'name' => 'x', 'ip' => '127.0.0.1', 'user_id' => \App\Models\User::factory()->create()->id]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = new \SimpleXMLElement($response->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $this->assertSame([url('/BIOT1')], array_map('strval', $xml->xpath('//s:loc')), 'only bio pages are listed');
        $this->get('/BIOT1')->assertOk()->assertSee('<meta name="robots" content="index, follow', false);
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_error_pages_follow_the_language_of_the_url(): void
    {
        $this->withHeader('Accept-Language', 'uz')->get('/zzzzz')->assertNotFound()->assertSee('Sahifa topilmadi');
        $this->get('/ru/created/NOPE22')->assertNotFound()->assertSee('Страница не найдена');
    }

    public function test_every_link_on_every_page_works(): void
    {
        $seen = [];
        $queue = ['/', '/ru', '/en', '/files/create', '/qr', '/codes/create', '/bio/create', '/invitations/create', '/login', '/register', '/ru/qr', '/en/bio/create'];

        while ($queue) {
            $path = array_shift($queue);
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            $response = $this->get($path);
            $this->assertContains($response->getStatusCode(), [200, 302], "$path returned " . $response->getStatusCode());

            if ($response->getStatusCode() !== 200) {
                continue;
            }

            preg_match_all('#<a [^>]*href="([^"]+)"#', $response->getContent(), $links);
            foreach ($links[1] as $href) {
                $href = html_entity_decode($href);
                if (str_starts_with($href, url('/'))) {
                    $href = substr($href, strlen(url('/')));
                }
                if (!str_starts_with($href, '/') || str_starts_with($href, '//')) {
                    continue; // external, mailto:, #anchor
                }
                $queue[] = '/' . ltrim(explode('#', $href)[0], '/');
            }
        }

        $this->assertGreaterThan(20, count($seen), 'the crawl should have found plenty of pages');
    }
}
