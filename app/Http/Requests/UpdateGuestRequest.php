<?php

namespace App\Http\Requests;

use App\Models\InvitationGuest;
use App\Support\GuestList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hosts editing one guest: contact details, and the reply when the guest answered by phone instead of
 * through the invitation page.
 */
class UpdateGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! GuestList::isPhone((string) $value)) {
                        $fail(__('Enter a phone number such as +998 90 123 45 67.'));
                    }
                },
            ],
            'status' => ['required', Rule::in(InvitationGuest::STATUSES)],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** What to save: a guest who is not coming brings nobody, a guest who is coming brings at least themselves. */
    public function guestData(InvitationGuest $guest): array
    {
        $data = $this->validated();
        $attending = $data['status'] === 'attending';

        $data['phone'] = isset($data['phone']) ? trim(preg_replace('/\s+/', ' ', $data['phone'])) : null;
        $data['party_size'] = $attending ? ($data['party_size'] ?? max(1, $guest->party_size)) : 0;
        // responded_at is when the guest answered on the invitation page, which is what the hosts are told is "new";
        // a reply the hosts typed in themselves does not count as news
        $data['responded_at'] = $data['status'] === 'pending' ? null : $guest->responded_at;

        return $data;
    }
}
