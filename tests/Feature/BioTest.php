<?php

namespace Tests\Feature;

use App\Models\Bio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BioTest extends TestCase
{
    use RefreshDatabase;

    private function bioWithKalta(array $attributes): Bio
    {
        $bio = Bio::create($attributes + ['title' => 'Test']);
        $bio->kalta()->create(['url' => 'BIO22', 'user_id' => 1, 'ip' => '127.0.0.1', 'description' => 'About me']);

        return $bio;
    }

    public function test_a_page_is_created_with_handles_turned_into_links_and_a_resized_photo(): void
    {
        Storage::fake('public');

        $this->post('/bio', [
            'title' => 'Aziz Karimov',
            'description' => 'Photographer',
            'instagram' => '@aziz.photo',
            'telegram' => 'aziz_k',
            'github' => 'github.com/azizk',
            'blog' => 'azizphoto.uz',
            'style' => 'sunset',
            'avatar_icon' => UploadedFile::fake()->image('me.jpg', 1600, 900),
        ])->assertRedirect();

        $bio = Bio::firstOrFail();
        $this->assertSame('https://instagram.com/aziz.photo', $bio->instagram);
        $this->assertSame('https://t.me/aziz_k', $bio->telegram);
        $this->assertSame('https://github.com/azizk', $bio->github);
        $this->assertSame('https://azizphoto.uz', $bio->blog);

        $this->assertStringStartsWith('media/avatars/', $bio->avatar_icon);
        $path = substr($bio->avatar_icon, strlen('media/'));
        Storage::disk('public')->assertExists($path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([512, 512], [$width, $height], 'cropped to a square and shrunk');

        $this->get('/' . $bio->kalta->url)
            ->assertOk()
            ->assertSee('Aziz Karimov')
            ->assertSee('data-bio-theme="sunset"', false)
            ->assertSee('href="https://instagram.com/aziz.photo"', false)
            ->assertSee('rel="noopener nofollow ugc"', false);
    }

    public function test_invalid_input_is_rejected(): void
    {
        Storage::fake('public');

        $this->post('/bio', ['title' => 'X', 'blog' => 'javascript:alert(1)'])->assertSessionHasErrors('blog');
        $this->post('/bio', ['description' => 'no title'])->assertSessionHasErrors('title');
        $this->post('/bio', ['title' => 'X', 'style' => 'rainbow'])->assertSessionHasErrors('style');
        $this->post('/bio', ['title' => 'X', 'avatar_icon' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar_icon');

        $this->assertSame(0, Bio::count());
    }

    public function test_the_description_limit_matches_the_database_column(): void
    {
        $this->post('/bio', ['title' => 'X', 'description' => str_repeat('d', 255)])->assertSessionHasNoErrors();
        $this->post('/bio', ['title' => 'Y', 'description' => str_repeat('d', 256)])->assertSessionHasErrors('description');
    }

    public function test_png_photos_become_jpegs_and_undecodable_ones_are_kept_as_uploaded(): void
    {
        Storage::fake('public');

        $this->post('/bio', ['title' => 'PNG', 'avatar_icon' => UploadedFile::fake()->image('t.png', 800, 600)])->assertRedirect();
        $this->assertStringEndsWith('.jpg', Bio::where('title', 'PNG')->first()->avatar_icon);

        // a JPEG whose pixel data is cut off passes validation but cannot be decoded: store the original
        $jpeg = UploadedFile::fake()->image('ok.jpg', 400, 300)->getContent();
        $broken = UploadedFile::fake()->createWithContent('broken.jpg', substr($jpeg, 0, 400));
        $this->post('/bio', ['title' => 'Broken', 'avatar_icon' => $broken])->assertRedirect();
        $path = Bio::where('title', 'Broken')->first()->avatar_icon;
        $this->assertStringStartsWith('media/avatars/', $path);
        $this->assertSame(substr($jpeg, 0, 400), Storage::disk('public')->get(substr($path, strlen('media/'))));
    }

    public function test_unsafe_links_that_are_already_stored_are_never_rendered(): void
    {
        $bio = $this->bioWithKalta([
            'blog' => 'javascript:alert(document.cookie)',
            'twitter' => 'data:text/html,<script>1</script>',
            'instagram' => 'https://instagram.com/safe',
            'style' => 'hack"onload="alert(1)',
        ]);

        $html = $this->get('/' . $bio->kalta->url)->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
        $this->assertStringContainsString('https://instagram.com/safe', $html);
        $this->assertStringContainsString('data-bio-theme="default"', $html);
    }

    public function test_old_numeric_addresses_redirect_to_the_short_link(): void
    {
        $bio = $this->bioWithKalta([]);
        $this->get("/bio/{$bio->id}")->assertStatus(301)->assertRedirect(url('/BIO22'));

        $orphan = Bio::create(['title' => 'Legacy page']);
        $this->get("/bio/{$orphan->id}")->assertOk()->assertSee('Legacy page');
    }

    public function test_uploaded_photos_are_served_with_long_cache_headers_and_no_path_tricks(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/a1b2.jpg', UploadedFile::fake()->image('a.jpg')->getContent());
        Storage::disk('public')->put('secret.txt', 'top secret');

        $this->get('/media/avatars/a1b2.jpg')->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->get('/media/avatars/missing.jpg')->assertNotFound();
        $this->get('/media/other/a1b2.jpg')->assertNotFound();
        $this->get('/media/avatars/..%2Fsecret.txt')->assertNotFound();
        $this->get('/media/avatars/%2e%2e/secret.txt')->assertNotFound();
    }
}
