<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Support\PreviewImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The picture and text chat apps show when somebody shares an invitation link.
 */
class InvitationPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createInvitation(array $overrides = []): Invitation
    {
        $this->post('/invitations', array_merge([
            'groom_name' => 'Aziz',
            'bride_name' => 'Malika',
            'event_at' => '2030-05-10T18:30',
            'venue' => 'Navruz restaurant',
            'address' => 'Main street 1',
            'locale' => 'uz',
            'guests' => 'Bobur',
        ], $overrides))->assertRedirect();

        return Invitation::latest('id')->firstOrFail();
    }

    private function picture(Invitation $invitation)
    {
        $response = $this->get(route('invitations.preview', $invitation->kalta->url))->assertOk();

        return [$response, file_get_contents($response->baseResponse->getFile()->getPathname())];
    }

    private function pixel(string $jpeg, int $x, int $y): array
    {
        $image = imagecreatefromstring($jpeg);
        $rgb = imagecolorat($image, $x, $y);

        return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
    }

    public function test_the_preview_is_a_picture_the_size_chat_apps_want(): void
    {
        $invitation = $this->createInvitation();

        [$response, $jpeg] = $this->picture($invitation);

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($jpeg), 0, 2));
        $this->assertLessThan(300 * 1024, strlen($jpeg), 'WhatsApp ignores big pictures');
        $this->assertTrue(Storage::disk('public')->exists('previews/' . $invitation->id . '-' . PreviewImage::version($invitation) . '.jpg'));
    }

    public function test_the_picture_is_drawn_once_and_kept(): void
    {
        $invitation = $this->createInvitation();

        [, $first] = $this->picture($invitation);
        $path = 'previews/' . $invitation->id . '-' . PreviewImage::version($invitation) . '.jpg';
        $made = Storage::disk('public')->lastModified($path);

        $this->travel(5)->minutes();
        [, $second] = $this->picture($invitation);

        $this->assertSame($first, $second);
        $this->assertSame($made, Storage::disk('public')->lastModified($path), 'not drawn again');
    }

    public function test_the_picture_gets_a_new_address_after_an_edit_and_the_old_one_is_removed(): void
    {
        $invitation = $this->createInvitation();
        $before = PreviewImage::version($invitation);
        $this->picture($invitation);

        $this->patch("/invitations/{$invitation->manage_token}", [
            'groom_name' => 'Aziz', 'bride_name' => 'Malika', 'event_at' => '2030-05-10T19:00', 'venue' => 'Another hall',
        ])->assertRedirect();

        $after = PreviewImage::version($invitation->fresh());
        $this->assertNotSame($before, $after, 'chat apps cache pictures by address, so an edit needs a new one');

        $this->get($invitation->kalta->short_url)->assertSee(route('invitations.preview', $invitation->kalta->url) . '?v=' . $after, false);
        $this->picture($invitation->fresh());

        $disk = Storage::disk('public');
        $this->assertTrue($disk->exists("previews/{$invitation->id}-$after.jpg"));
        $this->assertFalse($disk->exists("previews/{$invitation->id}-$before.jpg"));
    }

    public function test_things_that_are_not_on_the_picture_do_not_change_its_address(): void
    {
        $invitation = $this->createInvitation();
        $before = PreviewImage::version($invitation);

        $invitation->update(['message' => 'A new message', 'host_email' => 'x@example.com', 'share_message' => 'Hi {name}']);
        $invitation->guests()->create(['name' => 'Someone']);

        $this->assertSame($before, PreviewImage::version($invitation->fresh()));
    }

    public function test_link_previews_carry_the_details_in_words_too(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();
        $picture = route('invitations.preview', $invitation->kalta->url) . '?v=' . PreviewImage::version($invitation);

        foreach ([$guest->link(), $invitation->kalta->short_url] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<meta property="og:image" content="' . $picture . '">', false)
                ->assertSee('<meta property="og:title" content="' . e($this->uz('Wedding of :couple', ['couple' => 'Aziz & Malika'])) . ' · Kalta.uz">', false)
                ->assertSee('<meta property="og:description" content="Juma, 10 May 2030 · 18:30 · Navruz restaurant, Main street 1">', false)
                ->assertSee('<meta property="og:image:width" content="1200">', false);
        }
    }

    public function test_the_text_follows_the_invitation_language(): void
    {
        $uz = $this->createInvitation(['locale' => 'uz']);
        $this->get($uz->kalta->short_url)->assertSee('toʻyi', false)->assertSee('Juma, 10 May 2030 · 18:30', false)->assertDontSee('Wedding of');
    }

    public function test_a_cover_photo_goes_on_the_left_and_without_one_the_card_is_all_paper(): void
    {
        $plain = $this->createInvitation();
        [, $without] = $this->picture($plain);
        $this->assertEqualsWithDelta([251, 246, 236], $this->pixel($without, 5, 5), 3);
        $this->assertEqualsWithDelta([251, 246, 236], $this->pixel($without, 100, 300), 3);

        $photo = $this->createInvitation(['cover_image' => UploadedFile::fake()->image('c.jpg', 1600, 1000)->size(100)]);
        [, $with] = $this->picture($photo);
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($with), 0, 2));
        $this->assertEqualsWithDelta([251, 246, 236], $this->pixel($with, 1190, 620), 3, 'the right side is still the paper card');
        $this->assertNotEqualsWithDelta([251, 246, 236], $this->pixel($with, 100, 300), 40, 'the left side is the photo');
    }

    public function test_pictures_can_be_drawn_for_any_language_and_any_text(): void
    {
        $cases = [
            ['locale' => 'ru', 'groom_name' => 'Александр', 'bride_name' => 'Мария', 'venue' => 'Ресторан «Навруз»', 'address' => 'ул. Амира Темура, 108'],
            ['locale' => 'uz', 'groom_name' => 'Oʻgʻiloy', 'bride_name' => 'Shahrizoda', 'venue' => 'Sherdor to’yxonasi, massiv', 'address' => 'Sheroz toʻyxonasi'],
            ['locale' => 'uz', 'groom_name' => str_repeat('Abdurahmonovich ', 7), 'bride_name' => str_repeat('Shukurullayeva ', 7), 'venue' => str_repeat('A very long venue name ', 20), 'address' => str_repeat('x', 400)],
            ['locale' => 'uz', 'groom_name' => 'Zoë 🤍', 'bride_name' => 'Åsa 新娘', 'venue' => 'Café Ñandú', 'address' => ''],
            ['locale' => 'ru', 'groom_name' => 'Иван Петров', 'bride_name' => 'Anna-Мария', 'venue' => 'Hall №5 «Mix»', 'address' => null],
        ];

        foreach ($cases as $case) {
            $invitation = $this->createInvitation($case);
            [, $jpeg] = $this->picture($invitation);

            $this->assertSame([1200, 630], array_slice(getimagesizefromstring($jpeg), 0, 2), json_encode($case, JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_only_invitations_have_a_picture(): void
    {
        $code = $this->postJson('/kaltas/make', ['url' => 'https://example.com'])->json('code');

        $this->get("/preview/$code.jpg")->assertNotFound();
        $this->get('/preview/ZZZZZ.jpg')->assertNotFound();
    }

    public function test_deleting_the_invitation_removes_its_picture(): void
    {
        $invitation = $this->createInvitation();
        $other = $this->createInvitation(['groom_name' => 'Other']);
        $this->picture($invitation);
        $this->picture($other);

        $this->delete("/invitations/{$invitation->manage_token}")->assertRedirect();

        $files = Storage::disk('public')->files('previews');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith("previews/{$other->id}-", $files[0]);
    }

    public function test_the_fallback_is_the_cover_photo_when_pictures_cannot_be_drawn(): void
    {
        $invitation = $this->createInvitation(['cover_image' => UploadedFile::fake()->image('c.jpg', 800, 600)]);

        $this->assertTrue(PreviewImage::available());
        $this->get($invitation->kalta->short_url)->assertSee('/preview/', false);
        $this->assertFileExists(resource_path('fonts/cormorant-600-italic-latin.ttf'));
        $this->assertFileExists(resource_path('fonts/cormorant-600-italic-cyrillic.ttf'));
    }
}
