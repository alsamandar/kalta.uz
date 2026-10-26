<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Kalta;
use App\Models\Short;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function link(User $owner, string $code = 'AAAA2', string $url = 'https://example.com'): Kalta
    {
        return Short::create(['long_url' => $url])->kalta()->create(['url' => $code, 'user_id' => $owner->id, 'ip' => '127.0.0.1']);
    }

    public function test_the_dashboard_needs_a_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_people_see_only_their_own_links(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->link($me, 'MINE2', 'https://mine.example');
        $this->link($other, 'THEIR', 'https://theirs.example');

        $this->actingAs($me)->get('/dashboard')
            ->assertOk()
            ->assertSee('MINE2')
            ->assertSee('https://mine.example')
            ->assertDontSee('THEIR')
            ->assertSee('noindex', false);
    }

    public function test_links_created_while_logged_in_belong_to_the_user(): void
    {
        $me = User::factory()->create();

        $code = $this->actingAs($me)->postJson('/kaltas/make', ['url' => 'https://example.com'])->json('code');

        $this->assertSame($me->id, Kalta::where('url', $code)->first()->user_id);
    }

    public function test_owners_can_delete_their_links_and_files_are_removed_from_disk(): void
    {
        Storage::fake('public');
        $me = User::factory()->create();

        Storage::disk('public')->put('uploads/x.bin', 'data');
        $kalta = File::create(['name' => 'x.txt', 'path' => 'uploads/x.bin'])->kalta()->create(['url' => 'FILE2', 'user_id' => $me->id, 'ip' => '127.0.0.1']);

        $this->actingAs($me)->delete("/kaltas/{$kalta->url}")->assertRedirect();

        $this->assertNull(Kalta::find($kalta->id));
        $this->assertSame(0, File::count());
        Storage::disk('public')->assertMissing('uploads/x.bin');
    }

    public function test_nobody_can_delete_someone_elses_link(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $kalta = $this->link($owner);

        $this->actingAs($intruder)->delete("/kaltas/{$kalta->url}")->assertForbidden();

        $this->app['auth']->forgetGuards(); // back to an anonymous visitor
        $this->delete("/kaltas/{$kalta->url}")->assertRedirect('/login');

        $this->assertNotNull(Kalta::find($kalta->id));
    }

    public function test_the_list_is_paginated_with_the_site_styling(): void
    {
        $me = User::factory()->create();
        foreach (range(1, 17) as $i) {
            $this->link($me, 'LINK' . str_pad((string) $i, 2, '2', STR_PAD_LEFT), "https://example.com/$i");
        }

        $this->actingAs($me)->get('/dashboard')
            ->assertOk()
            ->assertSee('class="pagination"', false)
            ->assertDontSee('tailwind');
    }
}
