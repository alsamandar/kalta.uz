<?php

namespace Tests\Feature;

use App\Models\Kalta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LinkToolsTest extends TestCase
{
    use RefreshDatabase;

    // ---------- short links ----------

    public function test_json_request_creates_a_short_link_with_a_readable_code(): void
    {
        $response = $this->postJson('/kaltas/make', ['url' => 'example.com/page?x=1'])
            ->assertCreated()
            ->assertJsonStructure(['code', 'url', 'created_url', 'qr', 'long_url']);

        $this->assertSame('https://example.com/page?x=1', $response->json('long_url'), 'a missing scheme is added');
        // no 0/O or 1/I/L look-alikes
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{5}$/', $response->json('code'));
        $this->assertStringStartsWith('<svg', $response->json('qr'));

        $this->get('/' . $response->json('code'))
            ->assertStatus(302)
            ->assertRedirect('https://example.com/page?x=1')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_dangerous_or_invalid_links_are_rejected(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,hi', 'ftp://example.com/file', 'not a url', ''] as $bad) {
            $this->postJson('/kaltas/make', ['url' => $bad])->assertStatus(422)->assertJsonValidationErrors('url');
        }

        $this->assertSame(0, Kalta::count());
    }

    public function test_html_form_redirects_to_a_result_page_in_the_same_language(): void
    {
        $location = $this->post('/ru/kaltas/make', ['url' => 'https://example.com'])->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(url('/ru/created/'), $location);
        $this->get($location)->assertOk()->assertSee('<svg', false)->assertSee('Ваша короткая ссылка готова');
    }

    public function test_html_form_shows_validation_errors(): void
    {
        $this->from('/')->post('/kaltas/make', ['url' => 'javascript:alert(1)'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('url');
    }

    public function test_clicks_count_people_but_not_bots_or_previews(): void
    {
        $code = $this->postJson('/kaltas/make', ['url' => 'https://example.com'])->json('code');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120 Safari/537')->get("/$code");
        $this->withHeader('User-Agent', 'TelegramBot (like TwitterBot)')->get("/$code");
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get("/$code");

        $this->assertSame(1, Kalta::first()->clicks);
    }

    public function test_unknown_code_shows_a_friendly_404(): void
    {
        $this->withHeader('Accept-Language', 'uz')->get('/ZZZZZ')->assertNotFound()->assertSee('Sahifa topilmadi')->assertSee('noindex', false);
    }

    public function test_creating_links_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 20) as $i) {
            $this->postJson('/kaltas/make', ['url' => "https://example.com/$i"])->assertCreated();
        }

        $this->postJson('/kaltas/make', ['url' => 'https://example.com/21'])->assertStatus(429);
    }

    // ---------- file sharing ----------

    public function test_a_file_can_be_shared_and_is_served_as_a_download(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $response = $this->postJson('/files', ['file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')])
            ->assertCreated();

        $this->assertCount(1, Storage::disk('local')->files('uploads'));
        $this->assertStringEndsWith('.bin', Storage::disk('local')->files('uploads')[0], 'stored under a neutral name');
        $this->assertCount(0, Storage::disk('public')->files('uploads'), 'outside the web root');

        $this->get('/' . $response->json('code'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=report.pdf')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->get($response->json('created_url'))->assertOk()->assertSee('report.pdf');
    }

    public function test_programs_scripts_and_oversized_files_are_rejected(): void
    {
        Storage::fake('public');

        $this->postJson('/files', ['file' => UploadedFile::fake()->create('evil.php', 1)])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->postJson('/files', ['file' => UploadedFile::fake()->create('setup.EXE', 1)])->assertStatus(422);
        $this->postJson('/files', ['file' => UploadedFile::fake()->create('big.zip', upload_limit_kb() + 1)])->assertStatus(422);
        $this->postJson('/files', [])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->files('uploads'));
    }

    public function test_a_missing_file_on_disk_is_a_404_not_a_crash(): void
    {
        Storage::fake('public');
        $file = \App\Models\File::create(['name' => 'gone.txt', 'path' => 'uploads/gone.bin']);
        $kalta = $file->kalta()->create(['url' => 'GONE2', 'user_id' => 1, 'ip' => '127.0.0.1']);

        $this->get('/' . $kalta->url)->assertNotFound();
    }

    // ---------- text & code ----------

    public function test_a_snippet_is_escaped_unlisted_and_available_as_raw_text(): void
    {
        $location = $this->post('/codes', [
            'content' => "<script>alert(1)</script>\nsecond line",
            'language' => 'html',
            'expiration' => '1d',
        ])->assertRedirect()->headers->get('Location');
        $code = Str::afterLast($location, '/');

        $this->get("/$code")
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get("/$code/raw")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('second line');
    }

    public function test_a_snippet_expires(): void
    {
        $code = Str::afterLast($this->post('/codes', ['content' => 'temporary', 'expiration' => '10m'])->headers->get('Location'), '/');

        $this->get("/$code")->assertOk();

        $this->travel(11)->minutes();

        $this->get("/$code")->assertStatus(410);
        $this->get("/$code/raw")->assertNotFound();
    }

    public function test_snippet_input_is_validated_and_can_be_long(): void
    {
        $this->post('/codes', ['content' => ''])->assertSessionHasErrors('content');
        $this->post('/codes', ['content' => 'x', 'language' => 'cobol'])->assertSessionHasErrors('language');
        $this->post('/codes', ['content' => 'x', 'expiration' => 'forever'])->assertSessionHasErrors('expiration');

        $long = str_repeat("line of code\n", 2000); // far more than the old 255 characters
        $code = Str::afterLast($this->post('/codes', ['content' => $long])->headers->get('Location'), '/');
        $this->assertSame($long, $this->get("/$code/raw")->getContent());

        // indentation of the first line is part of the code; Windows line endings are normalised
        $code = Str::afterLast($this->post('/codes', ['content' => "    indented()\r\n    more()"])->headers->get('Location'), '/');
        $this->assertSame("    indented()\n    more()", $this->get("/$code/raw")->getContent());
    }

    // ---------- QR codes ----------

    public function test_qr_codes_are_generated_as_svg(): void
    {
        $this->get('/qr')->assertOk();

        $svg = $this->postJson('/qr', ['text' => 'https://kalta.uz'])->assertOk()->json('svg');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);

        $this->post('/qr', ['text' => 'hello'])->assertOk()->assertSee('<svg', false); // works without JavaScript

        $this->postJson('/qr', ['text' => ''])->assertStatus(422);
        $this->postJson('/qr', ['text' => str_repeat('a', 1001)])->assertStatus(422);
    }
}
