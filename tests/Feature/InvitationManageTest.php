<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\InvitationGuest;
use App\Models\Kalta;
use App\Support\GuestList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The hosts' side of wedding invitations: the dashboard, sending, tracking, editing, exporting, deleting.
 */
class InvitationManageTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function createInvitation(array $overrides = []): Invitation
    {
        $this->post('/invitations', array_merge([
            'groom_name' => 'Aziz',
            'bride_name' => 'Malika',
            'event_at' => '2030-05-10T18:30',
            'venue' => 'Navruz',
            'host_email' => 'host@example.com',
            'locale' => 'uz',
            'guests' => "Bobur, bobur@example.com\nDilnoza; +998 90 123 45 67\nSherzod",
        ], $overrides))->assertRedirect();

        return Invitation::latest('id')->firstOrFail();
    }

    private function manage(Invitation $invitation, string $path = 'manage'): string
    {
        return "/invitations/{$invitation->manage_token}/" . $path;
    }

    /** The invitation resource itself (PATCH to change it, DELETE to remove it). */
    private function invitationUrl(Invitation $invitation): string
    {
        return "/invitations/{$invitation->manage_token}";
    }

    // ---------------------------------------------------------------- the dashboard

    public function test_the_dashboard_lists_every_guest_with_a_stage_and_ready_made_messages(): void
    {
        $invitation = $this->createInvitation();
        $dilnoza = $invitation->guests()->where('name', 'Dilnoza')->first();

        $html = $this->get($this->manage($invitation))->assertOk()->assertSee('noindex', false)->getContent();

        $this->assertSame(3, substr_count($html, 'class="guest-card"'));
        $this->assertStringContainsString('data-stage="unsent"', $html);
        // the WhatsApp button opens the chat with this very guest because a phone number is known
        $this->assertStringContainsString('https://wa.me/998901234567?text=', $html);
        $this->assertStringContainsString('https://t.me/share/url?url=' . rawurlencode($dilnoza->link()), $html);
        $this->assertStringContainsString('data-text="' . e($this->uz(':name, you are invited to the wedding of :couple!', ['name' => 'Dilnoza', 'couple' => 'Aziz & Malika'])) . '"', $html);
    }

    public function test_the_dashboard_speaks_the_visitors_language(): void
    {
        $invitation = $this->createInvitation();

        $this->withUnencryptedCookie('lang', 'uz')->get($this->manage($invitation))->assertOk()->assertSee('Sizning taklifnomangiz')->assertSee('Yuborilmagan');
        $this->withUnencryptedCookie('lang', 'ru')->get($this->manage($invitation))->assertOk()->assertSee('Ваше приглашение')->assertSee('Не отправлено');
    }

    public function test_the_numbers_follow_each_guest_from_sent_to_replied(): void
    {
        $invitation = $this->createInvitation(['guests' => "A\nB\nC\nD\nE"]);
        [$a, $b, $c, $d, $e] = $invitation->guests()->orderBy('name')->get()->all();

        $b->update(['sent_at' => now(), 'sent_via' => 'telegram']);
        $c->update(['sent_at' => now(), 'opened_at' => now(), 'views' => 2]);
        $d->update(['status' => 'attending', 'party_size' => 3, 'responded_at' => now()]);   // replied: obviously got it and opened it
        $e->update(['status' => 'declined', 'party_size' => 0, 'responded_at' => now()]);

        $stats = $invitation->fresh('guests')->stats();

        $this->assertSame(5, $stats['total']);
        $this->assertSame(['unsent' => 1, 'waiting' => 2], ['unsent' => $stats['unsent'], 'waiting' => $stats['waiting']]);
        $this->assertSame(4, $stats['sent']);
        $this->assertSame(3, $stats['seen']);
        $this->assertSame(2, $stats['replied']);
        $this->assertSame([1, 3, 1], [$stats['attending'], $stats['people'], $stats['declined']]);
        $this->assertSame(['unsent', 'sent', 'seen', 'coming', 'declined'], $invitation->guests()->orderBy('name')->get()->map->stage()->all());
    }

    public function test_an_empty_guest_list_invites_the_hosts_to_add_guests(): void
    {
        $invitation = $this->createInvitation(['guests' => '']);

        $this->get($this->manage($invitation))->assertOk()->assertSee($this->uz('No guests yet. Add your guest list below and each guest gets a personal link.'))->assertDontSee('data-guest-tools="" hidden', false);
    }

    // ---------------------------------------------------------------- "sent" marks

    public function test_a_guest_is_marked_as_sent_once_and_the_hosts_can_take_it_back(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();
        $url = $this->manage($invitation, "guests/{$guest->id}/sent");

        $this->postJson($url, ['via' => 'telegram'])->assertOk()->assertJson(['stage' => 'sent', 'sent' => true, 'via' => 'telegram']);
        $firstTime = $guest->fresh()->sent_at;

        $this->travel(5)->minutes();
        $this->postJson($url, ['via' => 'whatsapp'])->assertOk()->assertJson(['via' => 'telegram']); // the first send stands
        $this->assertEquals($firstTime, $guest->fresh()->sent_at);

        $this->postJson($url, ['undo' => 1])->assertOk()->assertJson(['stage' => 'unsent', 'sent' => false]);
        $this->assertNull($guest->fresh()->sent_at);
        $this->assertNull($guest->fresh()->sent_via);
    }

    public function test_starting_a_guest_over_also_forgets_that_the_link_was_opened(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();
        $guest->update(['sent_at' => now(), 'opened_at' => now(), 'views' => 2]); // the hosts tried the link themselves

        $this->postJson($this->manage($invitation, "guests/{$guest->id}/sent"), ['undo' => 1])->assertJson(['stage' => 'unsent', 'sent' => false]);

        $guest->refresh();
        $this->assertSame([null, null, 0], [$guest->opened_at, $guest->sent_at, $guest->views]);
    }

    public function test_a_guest_who_replied_keeps_the_opened_mark(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();
        $guest->update(['sent_at' => now(), 'opened_at' => now(), 'views' => 1, 'status' => 'attending', 'party_size' => 2, 'responded_at' => now()]);

        $this->postJson($this->manage($invitation, "guests/{$guest->id}/sent"), ['undo' => 1])->assertJson(['stage' => 'coming']);

        $this->assertNull($guest->fresh()->sent_at);
        $this->assertNotNull($guest->fresh()->opened_at);
    }

    public function test_opened_guests_can_be_started_over_from_the_page(): void
    {
        $invitation = $this->createInvitation(['guests' => "Opened\nFresh\nReplied"]);
        $invitation->guests()->where('name', 'Opened')->first()->update(['opened_at' => now(), 'views' => 1]);
        $invitation->guests()->where('name', 'Replied')->first()->update(['status' => 'declined', 'party_size' => 0]);

        $html = $this->get($this->manage($invitation))->getContent();

        // "Mark as not sent" (undo) is offered to the opened guest; guests who replied have no such button
        $this->assertSame(1, substr_count($html, 'name="undo" value="1"'));
        $this->assertSame(2, substr_count($html, 'data-sent-toggle'), 'unsent + opened guests, not the one who replied');
        $this->assertStringContainsString('?preview=1" target="_blank"', $html, 'hosts can look at a guest page without counting as a visit');
    }

    public function test_marking_without_javascript_goes_back_to_the_guest(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();

        $this->post($this->manage($invitation, "guests/{$guest->id}/sent"))
            ->assertRedirect($this->manage($invitation) . '#guest-' . $guest->id);
        $this->assertSame('manual', $guest->fresh()->sent_via);
    }

    public function test_only_known_channels_and_own_guests_can_be_marked(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();
        $stranger = $this->createInvitation(['guests' => 'Stranger'])->guests->first();

        $this->postJson($this->manage($invitation, "guests/{$guest->id}/sent"), ['via' => 'carrier-pigeon'])->assertStatus(422);
        $this->postJson($this->manage($invitation, "guests/{$stranger->id}/sent"), ['via' => 'telegram'])->assertNotFound();
        $this->assertNull($stranger->fresh()->sent_at);
    }

    // ---------------------------------------------------------------- "seen" marks

    public function test_opening_the_personal_link_marks_the_guest_as_seen(): void
    {
        $guest = $this->createInvitation()->guests->first();

        $this->withHeader('User-Agent', self::CHROME)->get($guest->link())->assertOk();
        $guest->refresh();
        $this->assertSame(1, $guest->views);
        $this->assertNotNull($guest->opened_at);
        $this->assertSame('seen', $guest->stage());

        $first = $guest->opened_at;
        $this->travel(1)->hour();
        $this->withHeader('User-Agent', self::CHROME)->get($guest->link())->assertOk();
        $guest->refresh();
        $this->assertSame(2, $guest->views);
        $this->assertEquals($first, $guest->opened_at, 'the first visit is what counts as "opened"');
    }

    public function test_link_previews_crawlers_and_the_hosts_own_preview_do_not_count_as_seen(): void
    {
        $guest = $this->createInvitation()->guests->first();

        // Telegram and WhatsApp fetch the page to build the preview the moment the hosts send it
        $this->withHeader('User-Agent', 'TelegramBot (like TwitterBot)')->get($guest->link())->assertOk();
        $this->withHeader('User-Agent', 'WhatsApp/2.23.20 A')->get($guest->link())->assertOk();
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get($guest->link())->assertOk();
        $this->withHeader('User-Agent', self::CHROME)->get($guest->link() . '?preview=1')->assertOk();
        $this->withHeader('User-Agent', self::CHROME)->head($guest->link())->assertOk();

        $this->assertSame(0, $guest->fresh()->views);
        $this->assertNull($guest->fresh()->opened_at);
    }

    public function test_the_general_link_counts_visits_but_not_previews(): void
    {
        $kalta = $this->createInvitation()->kalta;

        $this->withHeader('User-Agent', self::CHROME)->get('/' . $kalta->url)->assertOk();
        $this->withHeader('User-Agent', self::CHROME)->get('/' . $kalta->url . '?preview=1')->assertOk();
        $this->withHeader('User-Agent', 'TelegramBot (like TwitterBot)')->get('/' . $kalta->url)->assertOk();

        $this->assertSame(1, (int) $kalta->fresh()->clicks);
    }

    // ---------------------------------------------------------------- adding guests

    public function test_the_guest_list_understands_the_ways_people_write_one(): void
    {
        $rows = GuestList::parse(implode("\n", [
            '1. Aziz Karimov, aziz@example.com',
            '2) Malika  Yusupova ;+998 90 123 45 67',
            '- Bobur | 90 555 66 77',
            '• Dilnoza',
            "Sherzod\t+998 91 111 22 33",
            'Karim +998 93 000 11 22',
            'Olim - olim@example.com',
            'Амир - 90 123 45 67',
            'Александр',
            '',
            '   ',
        ]));

        $this->assertSame([
            ['Aziz Karimov', 'aziz@example.com', null],
            ['Malika Yusupova', null, '+998 90 123 45 67'],
            ['Bobur', null, '90 555 66 77'],
            ['Dilnoza', null, null],
            ['Sherzod', null, '+998 91 111 22 33'],
            ['Karim', null, '+998 93 000 11 22'],
            ['Olim', 'olim@example.com', null],
            ['Амир', null, '90 123 45 67'],
            ['Александр', null, null], // a name ending in "р" must survive: its last byte is also a dash character's byte
        ], array_map(fn ($row) => [$row['name'], $row['email'], $row['phone']], $rows));
    }

    public function test_adding_guests_twice_adds_them_once_and_completes_what_is_missing(): void
    {
        $invitation = $this->createInvitation(['guests' => "Aziz Karimov\nMalika"]);

        $this->post($this->manage($invitation, 'guests'), ['guests' => "Aziz Karimov, +998 90 123 45 67\nMALIKA\nBobur - bobur@example.com"])
            ->assertRedirect($this->manage($invitation))
            ->assertSessionHas('success', fn ($message) => str_contains($message, $this->uz('Added :count guest(s).', ['count' => 1]))
                && str_contains($message, $this->uz('Added contact details to :count guest(s).', ['count' => 1]))
                && str_contains($message, $this->uz(':count already on the list.', ['count' => 1])));

        $guests = $invitation->guests()->orderBy('name')->get();
        $this->assertSame(['Aziz Karimov', 'Bobur', 'Malika'], $guests->pluck('name')->all());
        $this->assertSame('+998 90 123 45 67', $guests[0]->phone, 'a phone number is added to the guest who was listed without one');
        $this->assertSame('bobur@example.com', $guests[1]->email);

        // the same paste again changes nothing
        $this->post($this->manage($invitation, 'guests'), ['guests' => "Aziz Karimov, +998 90 123 45 67\nBobur, bobur@example.com"])
            ->assertSessionHas('error', fn ($message) => str_contains($message, $this->uz(':count already on the list.', ['count' => 2])));
        $this->assertSame(3, $invitation->guests()->count());
    }

    public function test_two_different_people_with_the_same_name_are_both_kept(): void
    {
        $invitation = $this->createInvitation(['guests' => 'Aziz, +998 90 111 11 11']);

        $this->post($this->manage($invitation, 'guests'), ['guests' => 'Aziz, +998 91 222 22 22']);

        $this->assertSame(2, $invitation->guests()->count());
    }

    public function test_names_with_different_apostrophes_are_the_same_name(): void
    {
        $invitation = $this->createInvitation(['guests' => 'Oʻgʻiloy']);

        $this->post($this->manage($invitation, 'guests'), ['guests' => "O’G’ILOY\no'g'iloy"]);

        $this->assertSame(1, $invitation->guests()->count());
    }

    public function test_the_list_limit_is_reported(): void
    {
        config(['kalta.max_guests' => 2]);
        $invitation = $this->createInvitation(['guests' => 'One']);

        $this->post($this->manage($invitation, 'guests'), ['guests' => "Two\nThree\nFour"])
            ->assertSessionHas('success', fn ($message) => str_contains($message, $this->uz('The list is full: :max guests at most.', ['max' => 2])));
        $this->assertSame(2, $invitation->guests()->count());
    }

    public function test_an_empty_paste_says_so(): void
    {
        $invitation = $this->createInvitation();

        $this->post($this->manage($invitation, 'guests'), ['guests' => " \n , \n"])->assertSessionHas('error', fn ($message) => str_contains($message, $this->uz('No guests found. Write one guest per line.')));
    }

    // ---------------------------------------------------------------- editing a guest

    public function test_the_hosts_can_correct_a_guest_and_record_a_reply_given_by_phone(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests()->where('name', 'Sherzod')->first();

        $this->get($this->manage($invitation, "guests/{$guest->id}/edit"))->assertOk()->assertSee('Sherzod')->assertSee('noindex', false);

        $this->patch($this->manage($invitation, "guests/{$guest->id}"), [
            'name' => 'Sherzod Aliyev', 'phone' => '+998  93 111 22 33', 'email' => 'sherzod@example.com',
            'status' => 'attending', 'party_size' => 3, 'note' => 'Called today',
        ])->assertRedirect($this->manage($invitation) . '#guest-' . $guest->id);

        $guest->refresh();
        $this->assertSame(['Sherzod Aliyev', '+998 93 111 22 33', 'sherzod@example.com', 'attending', 3, 'Called today'], [$guest->name, $guest->phone, $guest->email, $guest->status, $guest->party_size, $guest->note]);
        $this->assertNull($guest->responded_at, 'a reply the hosts typed in is not "new" to them');
        $this->assertSame('coming', $guest->stage());

        $this->patch($this->manage($invitation, "guests/{$guest->id}"), ['name' => 'Sherzod Aliyev', 'status' => 'declined', 'party_size' => 5]);
        $this->assertSame(0, $guest->fresh()->party_size, 'somebody who is not coming brings nobody');

        $this->patch($this->manage($invitation, "guests/{$guest->id}"), ['name' => 'Sherzod Aliyev', 'status' => 'pending']);
        $this->assertNull($guest->fresh()->responded_at);

        $answered = now()->subDay()->startOfSecond();
        $guest->update(['status' => 'attending', 'party_size' => 2, 'responded_at' => $answered]);
        $this->patch($this->manage($invitation, "guests/{$guest->id}"), ['name' => 'Sherzod Aliyev', 'status' => 'attending', 'party_size' => 4]);
        $this->assertEquals($answered, $guest->fresh()->responded_at, 'the time the guest answered stays');
    }

    public function test_a_guest_needs_a_name_and_real_contact_details(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();
        $url = $this->manage($invitation, "guests/{$guest->id}");

        $this->patch($url, ['name' => '', 'status' => 'pending'])->assertSessionHasErrors('name');
        $this->patch($url, ['name' => 'X', 'status' => 'pending', 'phone' => 'call me'])->assertSessionHasErrors('phone');
        $this->patch($url, ['name' => 'X', 'status' => 'pending', 'email' => 'nope'])->assertSessionHasErrors('email');
        $this->patch($url, ['name' => 'X', 'status' => 'maybe'])->assertSessionHasErrors('status');
        $this->patch($url, ['name' => 'X', 'status' => 'attending', 'party_size' => 99])->assertSessionHasErrors('party_size');
    }

    public function test_a_guest_of_another_invitation_cannot_be_edited_through_this_one(): void
    {
        $invitation = $this->createInvitation();
        $stranger = $this->createInvitation(['guests' => 'Stranger'])->guests->first();

        $this->get($this->manage($invitation, "guests/{$stranger->id}/edit"))->assertNotFound();
        $this->patch($this->manage($invitation, "guests/{$stranger->id}"), ['name' => 'Hacked', 'status' => 'pending'])->assertNotFound();
        $this->assertSame('Stranger', $stranger->fresh()->name);
    }

    // ---------------------------------------------------------------- editing the invitation

    public function test_the_edit_page_shows_the_details_as_the_hosts_typed_them(): void
    {
        $invitation = $this->createInvitation(['address' => 'Main street 1', 'message' => 'Welcome!', 'rsvp_deadline' => '2030-05-01']);

        $this->get($this->manage($invitation, 'edit'))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('value="Aziz"', false)
            ->assertSee('value="2030-05-10T18:30"', false)   // the wall-clock time in Tashkent, not UTC
            ->assertSee('value="2030-05-01"', false)
            ->assertSee('Main street 1')
            ->assertSee('Welcome!');
    }

    public function test_the_hosts_can_change_everything_about_the_invitation(): void
    {
        $invitation = $this->createInvitation();

        $this->patch($this->invitationUrl($invitation), [
            'groom_name' => 'Samandar', 'bride_name' => 'Sarvinoz', 'event_at' => '2030-06-01T19:00', 'venue' => 'Sherdor',
            'address' => 'Massiv', 'map_url' => 'yandex.uz/maps/-/CCUZ', 'message' => 'Welcome', 'host_email' => 'new@example.com',
            'rsvp_deadline' => '2030-05-20', 'locale' => 'uz', 'share_message' => 'Assalomu alaykum, {name}!',
        ])->assertRedirect($this->manage($invitation))->assertSessionHas('success', $this->uz('Changes saved.'));

        $invitation->refresh();
        $this->assertSame(['Samandar', 'Sarvinoz', 'Sherdor', 'uz', 'https://yandex.uz/maps/-/CCUZ'], [$invitation->groom_name, $invitation->bride_name, $invitation->venue, $invitation->locale, $invitation->map_url]);
        $this->assertSame('2030-06-01 14:00:00', $invitation->event_at->format('Y-m-d H:i:s'), 'stored as UTC');
        $this->assertSame('Samandar & Sarvinoz', $invitation->kalta->name, 'the dashboard name follows');
        $this->assertSame('Assalomu alaykum, Bobur!', $invitation->guests()->where('name', 'Bobur')->first()->setRelation('invitation', $invitation)->shareText());
        $this->assertNotNull($invitation->manage_token);
    }

    public function test_the_javascript_form_gets_the_redirect_as_json(): void
    {
        $invitation = $this->createInvitation();

        $this->patchJson($this->invitationUrl($invitation), [
            'groom_name' => 'A', 'bride_name' => 'B', 'event_at' => '2030-06-01T19:00', 'venue' => 'V', 'locale' => 'uz',
        ])->assertStatus(201)->assertJson(['redirect' => url($this->manage($invitation))]);
    }

    public function test_edits_are_validated(): void
    {
        $invitation = $this->createInvitation();
        $base = ['groom_name' => 'A', 'bride_name' => 'B', 'venue' => 'V', 'event_at' => '2030-06-01T19:00'];

        $this->patch($this->invitationUrl($invitation), array_merge($base, ['groom_name' => '']))->assertSessionHasErrors('groom_name');
        $this->patch($this->invitationUrl($invitation), array_merge($base, ['event_at' => '2001-01-01T10:00']))->assertSessionHasErrors('event_at');
        $this->patch($this->invitationUrl($invitation), array_merge($base, ['map_url' => 'javascript:alert(1)']))->assertSessionHasErrors('map_url');
        $this->patch($this->invitationUrl($invitation), array_merge($base, ['rsvp_deadline' => '2031-01-01']))->assertSessionHasErrors('rsvp_deadline');
        $this->patch($this->invitationUrl($invitation), array_merge($base, ['share_message' => str_repeat('x', 601)]))->assertSessionHasErrors('share_message');
        $this->assertSame('Aziz', $invitation->fresh()->groom_name);
    }

    public function test_a_typo_can_be_fixed_after_the_wedding_has_started(): void
    {
        $invitation = $this->createInvitation(['event_at' => now('Asia/Tashkent')->addDay()->format('Y-m-d\TH:i')]);
        $same = $invitation->localEventAt()->format('Y-m-d\TH:i');

        $this->travel(2)->days();

        // same date and time: only the venue changes, which is allowed although the date is now in the past
        $this->patch($this->invitationUrl($invitation), ['groom_name' => 'Aziz', 'bride_name' => 'Malika', 'venue' => 'Navruz restaurant', 'event_at' => $same])
            ->assertSessionHasNoErrors();
        $this->assertSame('Navruz restaurant', $invitation->fresh()->venue);

        // but moving it to another date in the past is still refused
        $this->patch($this->invitationUrl($invitation), ['groom_name' => 'Aziz', 'bride_name' => 'Malika', 'venue' => 'Navruz', 'event_at' => now('Asia/Tashkent')->subDays(5)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('event_at');
    }

    public function test_the_cover_photo_can_be_replaced_and_removed(): void
    {
        Storage::fake('public');
        $invitation = $this->createInvitation(['cover_image' => UploadedFile::fake()->image('one.jpg', 800, 600)]);
        $first = $invitation->cover_image;
        $disk = Storage::disk('public');
        $base = ['groom_name' => 'Aziz', 'bride_name' => 'Malika', 'venue' => 'Navruz', 'event_at' => '2030-05-10T18:30'];

        $this->assertTrue($disk->exists(substr($first, 6)));

        $this->patch($this->invitationUrl($invitation), $base + ['cover_image' => UploadedFile::fake()->image('two.jpg', 900, 600)])->assertSessionHasNoErrors();
        $second = $invitation->fresh()->cover_image;
        $this->assertNotSame($first, $second);
        $this->assertFalse($disk->exists(substr($first, 6)), 'the old photo is deleted');
        $this->assertTrue($disk->exists(substr($second, 6)));

        $this->patch($this->invitationUrl($invitation), $base)->assertSessionHasNoErrors();
        $this->assertSame($second, $invitation->fresh()->cover_image, 'saving without a new photo keeps the old one');

        $this->patch($this->invitationUrl($invitation), $base + ['remove_cover' => 1]);
        $this->assertNull($invitation->fresh()->cover_image);
        $this->assertFalse($disk->exists(substr($second, 6)));
    }

    public function test_only_the_secret_link_opens_the_edit_forms(): void
    {
        $invitation = $this->createInvitation();

        $this->get('/invitations/wrong/edit')->assertNotFound();
        $this->patch('/invitations/wrong', ['groom_name' => 'X'])->assertNotFound();
        $this->delete('/invitations/wrong')->assertNotFound();
        $this->get('/invitations/wrong/guests.csv')->assertNotFound();
        $this->assertSame('Aziz', $invitation->fresh()->groom_name);
    }

    // ---------------------------------------------------------------- deleting

    public function test_the_hosts_can_delete_the_invitation_and_all_guest_data(): void
    {
        Storage::fake('public');
        $invitation = $this->createInvitation(['cover_image' => UploadedFile::fake()->image('c.jpg', 800, 600)]);
        $cover = substr($invitation->cover_image, 6);
        $code = $invitation->kalta->url;
        $other = $this->createInvitation(['guests' => 'Keep me']);

        $this->delete($this->invitationUrl($invitation))->assertRedirect(route('home'))->assertSessionHas('success');

        $this->assertNull(Invitation::find($invitation->id));
        $this->assertSame(0, InvitationGuest::where('invitation_id', $invitation->id)->count());
        $this->assertNull(Kalta::where('url', $code)->first());
        $this->assertFalse(Storage::disk('public')->exists($cover));
        $this->get('/' . $code)->assertNotFound();
        $this->get($this->manage($invitation))->assertNotFound();

        // somebody else's invitation is untouched
        $this->assertSame(1, $other->guests()->count());
    }

    // ---------------------------------------------------------------- CSV

    public function test_the_guest_list_downloads_as_a_spreadsheet(): void
    {
        $invitation = $this->createInvitation(['guests' => "Bobur, bobur@example.com\nDilnoza, +998 90 123 45 67"]);
        $dilnoza = $invitation->guests()->where('name', 'Dilnoza')->first();
        $dilnoza->update(['status' => 'attending', 'party_size' => 2, 'note' => 'Congrats, "friends"', 'responded_at' => now()]);

        $response = $this->get($this->manage($invitation, 'guests.csv'))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'a BOM so that Excel reads the letters correctly');

        $lines = preg_split('/\r\n/', trim(substr($csv, 3)));
        $this->assertSame('Ism;Telefon;E-pochta;Javob;Kishilar;Tilaklar;Yuborilgan vaqt;Ochilgan vaqt;Javob vaqti;Shaxsiy havola', $lines[0], 'Uzbek Excel expects semicolons');
        $this->assertStringContainsString('Bobur;;bobur@example.com;' . $this->uz('No reply') . ';', $lines[1]);
        $this->assertStringContainsString('Dilnoza;+998 90 123 45 67;;' . $this->uz('Coming') . ';2;"Congrats, ""friends""";', $lines[2]);
        $this->assertStringContainsString($dilnoza->link(), $lines[2]);
    }

    public function test_uzbek_and_russian_spreadsheets_use_semicolons_like_their_excel(): void
    {
        $invitation = $this->createInvitation();

        $uz = $this->withUnencryptedCookie('lang', 'uz')->get($this->manage($invitation, 'guests.csv'))->streamedContent();
        $this->assertStringContainsString('Ism;Telefon;E-pochta;', $uz);

        $ru = $this->withUnencryptedCookie('lang', 'ru')->get($this->manage($invitation, 'guests.csv'))->streamedContent();
        $this->assertStringContainsString('Имя;Телефон;E-mail;', $ru);
    }

    public function test_a_spreadsheet_never_runs_what_a_guest_typed(): void
    {
        $invitation = $this->createInvitation(['guests' => '=cmd, +998 90 123 45 67']);
        $invitation->guests->first()->update(['note' => '=HYPERLINK("http://evil.example","click")']);
        $invitation->guests()->create(['name' => '@SUM(1+1)', 'note' => '-2+3']);

        $csv = $this->get($this->manage($invitation, 'guests.csv'))->streamedContent();

        $this->assertStringContainsString("'=cmd;+998 90 123 45 67", $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil.example\"\",\"\"click\"\")\"", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $this->assertStringContainsString("'-2+3", $csv);
        $this->assertStringNotContainsString(";=HYPERLINK", $csv);
    }

    // ---------------------------------------------------------------- calendar

    public function test_the_wedding_can_be_added_to_any_calendar(): void
    {
        $invitation = $this->createInvitation(['venue' => 'Navruz, hall 2', 'address' => 'Main street; 1', 'message' => "Line one\nLine two"]);
        $guest = $invitation->guests->first();

        foreach ([$invitation->kalta->short_url . '/event.ics', "/i/{$guest->token}/event.ics"] as $url) {
            $response = $this->get($url)->assertOk();
            $ics = $response->getContent();

            $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
            $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
            $this->assertStringContainsString("DTSTART:20300510T133000Z\r\n", $ics, '18:30 in Tashkent is 13:30 UTC');
            $this->assertStringContainsString("DTEND:20300510T173000Z\r\n", $ics);
            $this->assertStringContainsString('SUMMARY:' . $this->uz('Wedding of :couple', ['couple' => 'Aziz & Malika']), $ics);
            $this->assertStringContainsString('LOCATION:Navruz\, hall 2\, Main street\; 1', $ics, 'commas and semicolons are escaped');
            $this->assertStringContainsString('Line one\nLine two', $ics);
            $this->assertStringContainsString('BEGIN:VALARM', $ics);

            foreach (explode("\r\n", $ics) as $line) {
                $this->assertLessThanOrEqual(75, strlen($line), "folded: $line");
            }
        }
    }

    public function test_calendar_files_exist_only_for_invitations(): void
    {
        $code = $this->postJson('/kaltas/make', ['url' => 'https://example.com'])->json('code');

        $this->get("/$code/event.ics")->assertNotFound();
        $this->get('/ZZZZZ/event.ics')->assertNotFound();
        $this->get('/i/AAAAAAAAAAAA/event.ics')->assertNotFound();
    }

    public function test_the_calendar_button_opens_the_calendar_file(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first();

        $this->get($invitation->kalta->short_url)->assertSee($invitation->kalta->short_url . '/event.ics', false);
        $this->get($guest->link())->assertSee("/i/{$guest->token}/event.ics", false);
    }

    // ---------------------------------------------------------------- messages to guests

    public function test_the_message_is_in_the_invitation_language_unless_the_hosts_wrote_their_own(): void
    {
        $invitation = $this->createInvitation(['locale' => 'ru']);
        $guest = $invitation->guests->first();

        $this->assertSame('Bobur, приглашаем вас на свадьбу Aziz & Malika!', $guest->shareText());
        $this->assertSame($guest->shareText() . "\n" . $guest->shareLink(), $guest->shareMessage());

        $invitation->update(['share_message' => "Salom, {name}!\nSizni kutamiz."]);
        $this->assertSame("Salom, Bobur!\nSizni kutamiz.", $guest->fresh()->setRelation('invitation', $invitation->fresh())->shareText());
    }

    public function test_shared_links_carry_a_tag_that_changes_with_the_preview_so_chat_apps_show_the_new_card(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests()->where('name', 'Dilnoza')->first()->setRelation('invitation', $invitation);
        $tag = \App\Support\PreviewImage::version($invitation);

        $this->assertSame($guest->link() . '?v=' . $tag, $guest->shareLink());
        $this->assertStringNotContainsString('?v=', $guest->link(), 'the plain link stays plain (e-mails, previews)');
        $this->assertStringContainsString(rawurlencode($guest->link() . '?v=' . $tag), $guest->telegramShareUrl());
        $this->assertStringContainsString(rawurlencode($guest->shareLink()), $guest->whatsappShareUrl());
        $this->assertSame($invitation->kalta->short_url . '?v=' . $tag, $invitation->shareUrl());

        $html = $this->get($this->manage($invitation))->getContent();
        $this->assertStringContainsString('data-link="' . $guest->shareLink() . '"', $html);
        $this->assertStringContainsString('value="' . $invitation->shareUrl() . '"', $html);
        $this->assertStringNotContainsString('?v=' . $tag . '?', $html);

        $csv = $this->get($this->manage($invitation, 'guests.csv'))->streamedContent();
        $this->assertStringContainsString($guest->shareLink(), $csv);

        // an edit gives new addresses: that is what makes Telegram fetch the card again
        $this->patch($this->invitationUrl($invitation), ['groom_name' => 'Aziz', 'bride_name' => 'Malika', 'venue' => 'Another hall', 'event_at' => '2030-05-10T18:30']);
        $fresh = $invitation->fresh();
        $freshGuest = $guest->fresh()->setRelation('invitation', $fresh);
        $this->assertNotSame($guest->shareLink(), $freshGuest->shareLink());
    }

    public function test_pages_ignore_the_tag_and_carry_it_in_their_own_address(): void
    {
        $invitation = $this->createInvitation();
        $guest = $invitation->guests->first()->setRelation('invitation', $invitation);

        $this->withHeader('User-Agent', self::CHROME)->get($guest->shareLink())->assertOk()
            ->assertSee('<meta property="og:url" content="' . $guest->shareLink() . '">', false)
            ->assertSee('<link rel="canonical" href="' . $guest->shareLink() . '">', false);
        $this->assertSame(1, $guest->fresh()->views, 'opening it through a tagged link counts as usual');

        $this->get($invitation->shareUrl())->assertOk()->assertSee('<meta property="og:url" content="' . $invitation->shareUrl() . '">', false);
        $this->get($guest->link())->assertOk(); // links sent before the tag existed still work
    }

    public function test_phone_numbers_are_turned_into_whatsapp_numbers(): void
    {
        $guest = new InvitationGuest();

        foreach ([
            '+998 90 123 45 67' => '998901234567',
            '90 123 45 67' => '998901234567',   // no country code: an Uzbek number
            '0901234567' => '998901234567',
            '998 (90) 123-45-67' => '998901234567',
            '00998901234567' => '998901234567',
            '+7 999 123 45 67' => '79991234567',
            'call me' => null,
            '123' => null,
            '' => null,
        ] as $typed => $expected) {
            $guest->phone = $typed;
            $this->assertSame($expected, $guest->whatsappNumber(), "for '$typed'");
        }
    }

    // ---------------------------------------------------------------- e-mail

    public function test_a_dead_mail_server_is_given_up_on_after_a_few_tries(): void
    {
        $invitation = $this->createInvitation(['guests' => "A, a@example.com\nB, b@example.com\nC, c@example.com\nD, d@example.com\nE, e@example.com"]);

        Mail::shouldReceive('to')->times(3)->andThrow(new \RuntimeException('connection refused'));

        $this->post($this->manage($invitation, 'send'))
            ->assertRedirect($this->manage($invitation))
            ->assertSessionHas('error', fn ($message) => str_contains($message, $this->uz('E-mail could not be sent: the server has no working mail settings. Send the personal links through Telegram or WhatsApp instead.')));
    }

    public function test_the_page_only_offers_e_mail_when_somebody_has_an_address(): void
    {
        $withMail = $this->createInvitation();
        $this->get($this->manage($withMail))->assertSee($this->uz('E-mail invitations'));

        $without = $this->createInvitation(['guests' => 'Dilnoza, +998 90 123 45 67']);
        $this->get($this->manage($without))->assertDontSee($this->uz('E-mail invitations'));
    }

    public function test_sending_e_mail_marks_the_guests_as_sent_by_e_mail(): void
    {
        Mail::fake();
        $invitation = $this->createInvitation();

        $this->post($this->manage($invitation, 'send'))->assertSessionHas('success');

        $bobur = $invitation->guests()->where('name', 'Bobur')->first();
        $this->assertSame('email', $bobur->sent_via);
        $this->assertSame('sent', $bobur->stage());
    }
}
