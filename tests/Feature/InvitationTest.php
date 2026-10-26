<?php

namespace Tests\Feature;

use App\Mail\RsvpReceivedMail;
use App\Mail\WeddingInvitationMail;
use App\Models\Invitation;
use App\Models\InvitationGuest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function createInvitation(array $overrides = []): Invitation
    {
        $this->post('/invitations', array_merge([
            'groom_name' => 'Aziz',
            'bride_name' => 'Malika',
            'event_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'venue' => 'Navruz',
            'host_email' => 'host@example.com',
            'locale' => 'en',
            'guests' => "Bobur, bobur@example.com\nDilnoza; +998 90 123 45 67\n\n",
        ], $overrides))->assertRedirect();

        return Invitation::latest('id')->firstOrFail();
    }

    public function test_host_creates_an_invitation_with_guests_and_a_short_link(): void
    {
        $invitation = $this->createInvitation();

        $this->assertCount(2, $invitation->guests);
        $this->assertSame('bobur@example.com', $invitation->guests[0]->email);
        $this->assertSame('+998 90 123 45 67', $invitation->guests[1]->phone);

        $this->get('/' . $invitation->kalta->url)->assertOk()->assertSee('Malika')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get("/invitations/{$invitation->manage_token}/manage")->assertOk()->assertSee('Bobur')->assertSee('noindex', false);
    }

    public function test_the_manage_page_needs_the_secret_token(): void
    {
        $this->createInvitation();

        $this->get('/invitations/wrong-token/manage')->assertNotFound();
    }

    public function test_event_times_are_wall_clock_times_in_tashkent(): void
    {
        $invitation = $this->createInvitation(['event_at' => '2030-05-10T18:30']);

        $this->assertSame('2030-05-10 13:30:00', $invitation->event_at->format('Y-m-d H:i:s'), 'stored as UTC');
        $this->assertSame('18:30', $invitation->localEventAt()->format('H:i'));

        $guest = $invitation->guests->first();
        $this->get($guest->link())
            ->assertOk()
            ->assertSee('18:30')
            ->assertSee('data-countdown="2030-05-10T18:30:00+05:00"', false);
    }

    public function test_guests_see_the_invitation_in_the_language_the_hosts_chose(): void
    {
        $invitation = $this->createInvitation(['locale' => 'ru', 'event_at' => '2030-11-07T18:00']);
        $guest = $invitation->guests->first();

        $this->withHeader('Accept-Language', 'en')->get($guest->link())
            ->assertOk()
            ->assertSee('<html lang="ru"', false)
            ->assertSee('Дорогой(ая) Bobur')
            ->assertSee(Carbon::create(2030, 11, 7)->locale('ru')->translatedFormat('l, j F Y'));

        $uz = $this->createInvitation(['locale' => 'uz', 'event_at' => '2030-11-07T18:00']);
        $this->get($uz->guests->first()->link())
            ->assertOk()
            ->assertSee(Carbon::create(2030, 11, 7)->locale('uz_Latn')->translatedFormat('l, j F Y'));
    }

    public function test_emails_go_out_once_unless_resend_is_chosen_and_use_the_invitation_language(): void
    {
        Mail::fake();
        $invitation = $this->createInvitation(['locale' => 'ru']);

        $this->post("/invitations/{$invitation->manage_token}/send")->assertRedirect()->assertSessionHas('success');
        Mail::assertSent(WeddingInvitationMail::class, 1);
        Mail::assertSent(WeddingInvitationMail::class, fn ($mail) => $mail->hasTo('bobur@example.com') && $mail->locale === 'ru');
        $this->assertNotNull($invitation->guests()->where('name', 'Bobur')->first()->sent_at);

        $this->post("/invitations/{$invitation->manage_token}/send");
        Mail::assertSent(WeddingInvitationMail::class, 1);

        $this->post("/invitations/{$invitation->manage_token}/send", ['resend' => 1]);
        Mail::assertSent(WeddingInvitationMail::class, 2);
    }

    public function test_the_invitation_email_contains_the_personal_link(): void
    {
        $guest = $this->createInvitation()->guests->first();

        // the controller sends it with ->locale($invitation->locale); do the same here
        (new WeddingInvitationMail($guest))->locale('en')->assertSeeInHtml($guest->link())->assertSeeInHtml('Dear Bobur');
        (new WeddingInvitationMail($guest))->locale('ru')->assertSeeInHtml('Дорогой(ая) Bobur')->assertSeeInHtml('Открыть приглашение и ответить');
    }

    public function test_a_failing_mail_server_is_reported_not_fatal(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('connection refused'));
        $invitation = $this->createInvitation();

        $this->post("/invitations/{$invitation->manage_token}/send")
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Bobur'));
    }

    public function test_guest_replies_and_the_host_is_notified(): void
    {
        Mail::fake();
        $guest = $this->createInvitation()->guests->first();

        $this->get("/i/{$guest->token}")->assertOk()->assertSee('Dear Bobur');

        $this->post("/i/{$guest->token}/rsvp", ['status' => 'attending', 'party_size' => 2, 'note' => 'Congrats!'])->assertRedirect();

        $guest->refresh();
        $this->assertSame(['attending', 2, 'Congrats!'], [$guest->status, $guest->party_size, $guest->note]);
        Mail::assertSent(RsvpReceivedMail::class, fn ($mail) => $mail->hasTo('host@example.com'));

        $this->post("/i/{$guest->token}/rsvp", ['status' => 'declined'])->assertRedirect();
        $this->assertSame(0, $guest->fresh()->party_size);
    }

    public function test_replies_close_after_the_deadline(): void
    {
        $guest = $this->createInvitation(['rsvp_deadline' => now()->addDay()->toDateString()])->guests->first();

        $this->travel(3)->days();

        $this->post("/i/{$guest->token}/rsvp", ['status' => 'declined'])->assertForbidden();
    }

    public function test_the_general_link_has_no_reply_form(): void
    {
        $invitation = $this->createInvitation();

        $this->get('/' . $invitation->kalta->url)->assertOk()->assertDontSee('name="status"', false);
        $this->get($invitation->guests->first()->link())->assertOk()->assertSee('name="status"', false);
    }

    public function test_validation(): void
    {
        $base = ['groom_name' => 'A', 'bride_name' => 'B', 'venue' => 'V'];

        $this->post('/invitations', $base + ['event_at' => now()->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('event_at');
        $this->post('/invitations', $base + ['event_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'map_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('map_url');

        // a map link pasted without "https://" is fine
        $invitation = $this->createInvitation(['map_url' => 'yandex.uz/maps/-/CCUZ']);
        $this->assertSame('https://yandex.uz/maps/-/CCUZ', $invitation->map_url);
    }

    public function test_the_guest_list_is_capped_and_guests_can_be_removed(): void
    {
        config(['kalta.max_guests' => 2]);
        $invitation = $this->createInvitation(['guests' => "One\nTwo\nThree\nFour"]);
        $this->assertSame(2, $invitation->guests()->count());

        $guest = $invitation->guests->first();
        $this->delete("/invitations/{$invitation->manage_token}/guests/{$guest->id}")->assertRedirect();
        $this->assertSame(1, $invitation->guests()->count());

        // a guest of another invitation cannot be removed through this one
        $other = $this->createInvitation(['guests' => 'Stranger']);
        $stranger = $other->guests->first();
        $this->delete("/invitations/{$invitation->manage_token}/guests/{$stranger->id}")->assertNotFound();
        $this->assertSame(1, $other->guests()->count());
    }

    public function test_the_cover_photo_is_resized_and_stored_outside_public(): void
    {
        Storage::fake('public');
        $invitation = $this->createInvitation(['cover_image' => UploadedFile::fake()->image('cover.jpg', 3000, 2000)]);

        $this->assertStringStartsWith('media/covers/', $invitation->cover_image);
        [$width] = getimagesizefromstring(Storage::disk('public')->get(substr($invitation->cover_image, strlen('media/'))));
        $this->assertSame(1600, $width);
    }

    public function test_guest_pages_have_tokens_that_cannot_be_guessed(): void
    {
        $guest = $this->createInvitation()->guests->first();

        $this->assertSame(12, strlen($guest->token));
        $this->get('/i/AAAAAAAAAAAA')->assertNotFound();
        $this->assertInstanceOf(InvitationGuest::class, $guest);
    }
}
