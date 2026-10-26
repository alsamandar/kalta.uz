<?php

namespace Tests\Feature;

use App\Models\Bio;
use App\Models\Code;
use App\Models\File;
use App\Models\Invitation;
use App\Models\Short;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'b6a1a3de-1111-4222-8333-444455556666';

    private function invitation(array $extra = []): array
    {
        return $extra + [
            'groom_name' => 'Aziz', 'bride_name' => 'Malika', 'venue' => 'Navruz',
            'event_at' => now()->addMonth()->format('Y-m-d\TH:i'),
        ];
    }

    public function test_sending_a_form_twice_creates_it_once(): void
    {
        // a retry after a lost connection, or an impatient double tap
        $payload = $this->invitation(['_submission' => self::ID]);

        $first = $this->post('/invitations', $payload)->assertRedirect();
        $second = $this->post('/invitations', $payload)->assertRedirect();

        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame(1, Invitation::count());
        $this->assertSame(1, \App\Models\Kalta::count());
    }

    public function test_every_create_endpoint_is_protected(): void
    {
        Storage::fake('public');
        $cases = [
            ['/kaltas/make', ['url' => 'https://example.com'], Short::class],
            ['/codes', ['content' => 'hello'], Code::class],
            ['/bio', ['title' => 'Aziz'], Bio::class],
            ['/files', ['file' => UploadedFile::fake()->create('a.pdf', 10)], File::class],
        ];

        foreach ($cases as [$url, $data, $model]) {
            $id = sprintf('%s-0000-4000-8000-000000000000', substr(md5($model), 0, 8));
            $first = $this->postJson($url, $data + ['_submission' => $id])->assertSuccessful();
            $second = $this->postJson($url, $data + ['_submission' => $id])->assertSuccessful();

            $this->assertSame($first->json(), $second->json(), "$url returns the first result again");
            $this->assertSame(1, $model::count(), "$url created exactly one record");
        }
    }

    public function test_a_new_id_or_different_data_is_a_new_submission(): void
    {
        $base = $this->invitation(['_submission' => self::ID]);

        $this->post('/invitations', $base);
        $this->post('/invitations', ['_submission' => 'another-id-1234567890'] + $base);   // new id
        $this->post('/invitations', ['venue' => 'A different hall'] + $base);              // Back, edit, submit again

        $this->assertSame(3, Invitation::count());
    }

    public function test_without_an_id_every_post_creates_something(): void
    {
        $this->post('/invitations', $this->invitation());
        $this->post('/invitations', $this->invitation());
        $this->post('/invitations', $this->invitation(['_submission' => 'x'])); // malformed ids are ignored

        $this->assertSame(3, Invitation::count());
    }

    public function test_a_failed_validation_does_not_use_up_the_id(): void
    {
        $this->post('/invitations', ['_submission' => self::ID] + $this->invitation(['venue' => '']))->assertSessionHasErrors('venue');
        $this->assertSame(0, Invitation::count());

        $this->post('/invitations', ['_submission' => self::ID] + $this->invitation())->assertSessionHasNoErrors();
        $this->assertSame(1, Invitation::count());
    }

    public function test_the_long_forms_answer_the_javascript_submit_with_json(): void
    {
        $this->postJson('/codes', ['content' => 'x'])->assertCreated()->assertJsonStructure(['redirect']);
        $this->postJson('/bio', ['title' => 'T'])->assertCreated()->assertJsonPath('redirect', fn ($url) => str_contains($url, '/created/'));

        $response = $this->postJson('/invitations', $this->invitation(['_submission' => self::ID]))->assertCreated();
        $this->assertStringContainsString('/invitations/', $response->json('redirect'));
        $this->assertStringEndsWith('/manage', $response->json('redirect'));

        $this->postJson('/codes', ['content' => ''])->assertStatus(422)->assertJsonValidationErrors('content');
        $this->postJson('/invitations', ['venue' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['groom_name', 'bride_name', 'event_at']);
    }

    public function test_the_success_message_survives_the_javascript_submit(): void
    {
        $url = $this->postJson('/invitations', $this->invitation(['_submission' => self::ID]))->json('redirect');

        $this->get($url)->assertOk()->assertSee('Taklifnoma yaratildi', false); // flashed by the POST, shown on the next page
    }

    public function test_the_connection_check_file_exists(): void
    {
        $this->assertFileExists(public_path('up.txt'));
    }
}
