<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Kalta;
use App\Models\Post;
use App\Models\User;
use App\Support\PostPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    private function longText(int $words = 80): string
    {
        return implode(' ', array_fill(0, $words, 'soʻz'));
    }

    private function publish(array $data): Post
    {
        $this->post('/posts', $data + ['format' => 'html'])->assertRedirect();

        return Post::latest('id')->firstOrFail();
    }

    public function test_anyone_can_publish_a_post_at_a_readable_address(): void
    {
        $response = $this->post('/posts', [
            'title' => 'Mening birinchi postim',
            'author' => 'Aziz',
            'body' => "Birinchi xatboshi.\n\n<b>Ikkinchi</b> xatboshi.",
        ]);

        $post = Post::firstOrFail();
        $url = $post->kalta->url;
        $this->assertSame('mening-birinchi-postim-' . now(config('kalta.timezone'))->format('m-d'), $url);
        $response->assertRedirect(url($url))->assertCookie(Post::COOKIE);

        // plain text (the form without JavaScript): paragraphs, and markup shown as text
        $this->assertSame('<p>Birinchi xatboshi.</p><p>&lt;b&gt;Ikkinchi&lt;/b&gt; xatboshi.</p>', $post->body);

        $this->get('/' . $url)
            ->assertOk()
            ->assertSee('<h1>Mening birinchi postim</h1>', false)
            ->assertSee('Aziz')
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('<link rel="canonical" href="' . url($url) . '">', false);
    }

    public function test_the_address_is_transliterated_and_never_taken_twice(): void
    {
        $first = $this->publish(['title' => 'Привет, мир', 'body' => '<p>Bir</p>']);
        $second = $this->publish(['title' => 'Привет, мир', 'body' => '<p>Ikki</p>']);
        $emoji = $this->publish(['title' => '🙂🙂', 'body' => '<p>Uch</p>']);

        $date = now(config('kalta.timezone'))->format('m-d');
        $this->assertSame("privet-mir-$date", $first->kalta->url);
        $this->assertSame("privet-mir-$date-2", $second->kalta->url);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $emoji->kalta->url);
    }

    public function test_nothing_that_could_run_survives_in_the_body(): void
    {
        $post = $this->publish([
            'title' => 'XSS',
            'body' => '<p onclick="steal()">Salom <a href="javascript:alert(1)">bos</a> <a href="https://example.com">sayt</a></p>'
                . '<script>alert(1)</script><iframe src="https://evil.example"></iframe><img src="https://evil.example/x.png" onerror="x()">'
                . '<h1>Katta</h1><div style="color:red">Div</div>',
        ]);

        $this->assertStringNotContainsString('script', $post->body);
        $this->assertStringNotContainsString('iframe', $post->body);
        $this->assertStringNotContainsString('onclick', $post->body);
        $this->assertStringNotContainsString('onerror', $post->body);
        $this->assertStringNotContainsString('javascript:', $post->body);
        $this->assertStringNotContainsString('evil.example', $post->body);
        $this->assertStringNotContainsString('style=', $post->body);
        $this->assertStringContainsString('Salom bos <a href="https://example.com" rel="nofollow ugc noopener" target="_blank">sayt</a>', $post->body);
        $this->assertStringContainsString('<h3>Katta</h3><p>Div</p>', $post->body);
    }

    public function test_an_empty_post_is_rejected(): void
    {
        $this->postJson('/posts', ['title' => 'Boʻsh', 'body' => '<p><br></p><script>x</script>', 'format' => 'html'])
            ->assertStatus(422)->assertJsonValidationErrors('body');
        $this->assertSame(0, Post::count());
    }

    public function test_only_the_browser_that_wrote_it_can_edit_an_anonymous_post(): void
    {
        $post = $this->publish(['title' => 'Eski', 'body' => '<p>Matn</p>']);
        $url = $post->kalta->url;

        $this->get("/posts/{$post->id}/edit")->assertForbidden();
        $this->get('/' . $url)->assertDontSee(route('posts.edit', $post));
        $this->patch("/posts/{$post->id}", ['title' => 'Buzildi', 'body' => 'x'])->assertForbidden();

        $this->withCookie(Post::COOKIE, json_encode([$post->edit_token]));
        $this->get('/' . $url)->assertSee(route('posts.edit', $post));
        $this->get("/posts/{$post->id}/edit")->assertOk()->assertSee('Eski')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->patch("/posts/{$post->id}", ['title' => 'Yangi', 'body' => '<p>Yangi matn</p>', 'format' => 'html'])
            ->assertRedirect(url($url));

        $post->refresh();
        $this->assertSame('Yangi', $post->title);
        $this->assertSame($url, $post->kalta->url, 'the address stays when the title changes');
    }

    public function test_a_logged_in_author_edits_from_anywhere_and_others_cannot(): void
    {
        $author = User::factory()->create();
        $this->actingAs($author)->post('/posts', ['title' => 'Akkauntdan', 'body' => 'Matn']);
        $post = Post::firstOrFail();
        $this->assertSame($author->id, $post->kalta->user_id);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($author)->get("/posts/{$post->id}/edit")->assertOk();

        $this->actingAs(User::factory()->create())->get("/posts/{$post->id}/edit")->assertForbidden();
    }

    public function test_pictures_are_uploaded_shrunk_and_used_for_the_link_preview(): void
    {
        Storage::fake('public');

        $src = $this->postJson('/posts/images', ['image' => UploadedFile::fake()->image('photo.jpg', 3000, 2000)])
            ->assertCreated()->json('src');
        $this->assertMatchesRegularExpression('#^/media/posts/[A-Za-z0-9_-]+\.jpg$#', $src);
        [$width] = getimagesizefromstring(Storage::disk('public')->get(substr($src, strlen('/media/'))));
        $this->assertSame(1600, $width);

        $post = $this->publish(['title' => 'Rasmli', 'body' => '<p>' . $this->longText() . '</p><figure><img src="' . $src . '"></figure>']);
        $this->assertSame(ltrim($src, '/'), $post->image);

        $this->get('/' . $post->kalta->url)
            ->assertSee('<meta property="og:image" content="' . asset(ltrim($src, '/')) . '">', false)
            ->assertSee('<meta property="og:image:width" content="1600">', false);

        $this->postJson('/posts/images', ['image' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])
            ->assertStatus(422);
    }

    public function test_a_post_without_a_photo_gets_a_drawn_preview(): void
    {
        if (! PostPreview::available()) {
            $this->markTestSkipped('GD with FreeType is not available');
        }
        Storage::fake('public');

        $post = $this->publish(['title' => 'Oʻzbekcha va Русский sarlavha', 'author' => 'Aziz', 'body' => '<p>Qisqa matn.</p>']);
        $this->get('/' . $post->kalta->url)->assertSee(PostPreview::url($post), false);

        $response = $this->get(route('posts.preview', $post))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_long_posts_are_indexed_and_listed_short_ones_are_not(): void
    {
        $long = $this->publish(['title' => 'Uzun', 'body' => '<p>' . $this->longText() . '</p>']);
        $short = $this->publish(['title' => 'Qisqa', 'body' => '<p>Bir necha soʻz.</p>']);

        $this->get('/' . $long->kalta->url)->assertSee('<meta name="robots" content="index, follow', false);
        $this->get('/' . $short->kalta->url)->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/sitemap.xml')
            ->assertSee('<loc>' . url($long->kalta->url) . '</loc>', false)
            ->assertDontSee(url($short->kalta->url), false);
    }

    public function test_deleting_a_post_removes_its_pictures(): void
    {
        Storage::fake('public');
        $src = $this->postJson('/posts/images', ['image' => UploadedFile::fake()->image('a.jpg', 800, 600)])->json('src');
        $path = substr($src, strlen('/media/'));
        $post = $this->publish(['title' => 'Oʻchadi', 'body' => '<figure><img src="' . $src . '"></figure>']);
        $this->withCookie(Post::COOKIE, json_encode([$post->edit_token]));

        // a picture taken out while editing is deleted too
        $this->patch("/posts/{$post->id}", ['title' => 'Oʻchadi', 'body' => '<p>Rasmsiz</p>', 'format' => 'html']);
        Storage::disk('public')->assertMissing($path);

        $this->delete("/posts/{$post->id}")->assertRedirect();
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Kalta::count());

        // deleting a post deletes its pictures, unless another post shows the same one
        $src = $this->postJson('/posts/images', ['image' => UploadedFile::fake()->image('b.jpg', 800, 600)])->json('src');
        $path = substr($src, strlen('/media/'));
        $one = $this->publish(['title' => 'Bir', 'body' => '<figure><img src="' . $src . '"></figure>']);
        $two = $this->publish(['title' => 'Ikki', 'body' => '<figure><img src="' . $src . '"></figure>']);
        $this->withCookie(Post::COOKIE, json_encode([$one->edit_token, $two->edit_token]));
        $this->delete("/posts/{$one->id}");
        Storage::disk('public')->assertExists($path);
        $this->delete("/posts/{$two->id}");
        Storage::disk('public')->assertMissing($path);
    }

    public function test_files_are_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->post('/files', ['file' => UploadedFile::fake()->create('passport.pdf', 20, 'application/pdf')])->assertRedirect();
        $file = File::firstOrFail();
        $code = $file->kalta->url;

        $this->assertSame(8, strlen($code), 'long enough not to be guessed');
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);

        $response = $this->get('/' . $code)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        $this->get('/sitemap.xml')->assertDontSee($code);
        $this->get('/robots.txt')->assertSee('Disallow: /storage/');
    }

    public function test_older_files_on_the_public_disk_still_download_and_can_be_moved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('public')->put('uploads/old.bin', 'old contents');
        $file = File::create(['path' => 'uploads/old.bin', 'name' => 'old.txt']);
        $file->kalta()->create(['url' => 'OLD22', 'user_id' => 1, 'ip' => '127.0.0.1']);

        $this->get('/OLD22')->assertOk()->assertDownload('old.txt');

        $this->artisan('files:make-private')->assertSuccessful();
        Storage::disk('public')->assertMissing('uploads/old.bin');
        $this->assertSame('old contents', Storage::disk('local')->get('uploads/old.bin'));
        $this->get('/OLD22')->assertOk()->assertDownload('old.txt');
    }

    public function test_the_dashboard_lists_posts_with_an_edit_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['title' => 'Kabinetdagi post', 'body' => 'Matn']);
        $post = Post::firstOrFail();

        $this->actingAs($user)->get('/dashboard')->assertSee('Kabinetdagi post')->assertSee(route('posts.edit', $post), false);
        $this->actingAs($user)->delete('/kaltas/' . $post->kalta->url)->assertRedirect();
        $this->assertSame(0, Post::count());
    }

    public function test_the_editor_page_is_available_in_every_language(): void
    {
        foreach (['/posts/create' => 'Post yozish', '/ru/posts/create' => 'Написать пост', '/en/posts/create' => 'Write a post'] as $path => $title) {
            $this->get($path)->assertOk()->assertSee($title)->assertSee('data-post-body', false);
        }
        $this->assertTrue(Str::contains($this->get('/')->getContent(), route('posts.create')));
    }
}
