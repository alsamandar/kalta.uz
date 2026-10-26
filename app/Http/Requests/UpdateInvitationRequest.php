<?php

namespace App\Http\Requests;

use App\Support\Images;
use Carbon\Carbon;

/**
 * Hosts correcting their invitation. Same fields as when it was created, plus replacing or removing the cover
 * photo and the wording of the message they send to guests.
 */
class UpdateInvitationRequest extends StoreInvitationRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['guests']);

        // The date must be in the future, but only when it is being changed: fixing a typo in the venue on the
        // wedding day itself has to keep working.
        $rules['event_at'] = [
            'required',
            'date',
            function (string $attribute, mixed $value, \Closure $fail) {
                try {
                    $new = Carbon::parse($value, config('kalta.timezone'))->utc();
                    $unchanged = $new->format('Y-m-d H:i') === $this->route('invitation')->event_at->format('Y-m-d H:i');

                    if (! $unchanged && $new->isPast()) {
                        $fail(__('The event date must be in the future.'));
                    }
                } catch (\Throwable $e) {
                    // the "date" rule reports unparseable values
                }
            },
        ];
        $rules['remove_cover'] = ['nullable', 'boolean'];
        $rules['share_message'] = ['nullable', 'string', 'max:600'];

        return $rules;
    }

    /**
     * The attributes to save; a new cover photo replaces the old one, "remove" clears it.
     */
    public function invitationData(): array
    {
        $data = collect($this->validated())->except(['cover_image', 'remove_cover'])->all();
        $data['locale'] ??= $this->route('invitation')->locale;

        if ($this->hasFile('cover_image')) {
            $data['cover_image'] = Images::cover($this->file('cover_image'));
        } elseif ($this->boolean('remove_cover')) {
            $data['cover_image'] = null;
        }

        return $data;
    }
}
