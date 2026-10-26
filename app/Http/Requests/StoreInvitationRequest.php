<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Images;
use Carbon\Carbon;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** Let people paste "yandex.uz/maps/..." without the scheme. */
    protected function prepareForValidation(): void
    {
        $this->merge(['map_url' => normalize_url($this->input('map_url'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'groom_name' => ['required', 'string', 'max:120'],
            'bride_name' => ['required', 'string', 'max:120'],
            'event_at' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) {
                    try {
                        if (Carbon::parse($value, config('kalta.timezone'))->isPast()) {
                            $fail(__('The event date must be in the future.'));
                        }
                    } catch (\Throwable $e) {
                        // the "date" rule reports unparseable values
                    }
                },
            ],
            'venue' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:http,https', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'host_email' => ['nullable', 'email', 'max:255'],
            'rsvp_deadline' => ['nullable', 'date', 'before_or_equal:event_at'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'guests' => ['nullable', 'string', 'max:20000'],
            'locale' => ['nullable', 'in:' . implode(',', array_keys(config('kalta.locales')))],
        ];
    }

    /**
     * Get the validated invitation attributes, storing the cover image if uploaded.
     */
    public function invitationData(): array
    {
        $data = collect($this->validated())->except(['guests', 'cover_image'])->all();
        $data['locale'] ??= app()->getLocale();

        if ($this->hasFile('cover_image')) {
            $data['cover_image'] = Images::cover($this->file('cover_image'));
        }

        return $data;
    }
}
