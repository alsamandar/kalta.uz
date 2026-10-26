<?php

namespace App\Http\Requests;

use App\Models\Bio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBioRequest extends FormRequest
{
    /** Where a bare "@handle" points to. */
    private const PROFILE_URLS = [
        'instagram' => 'https://instagram.com/',
        'telegram' => 'https://t.me/',
        'twitter' => 'https://x.com/',
        'github' => 'https://github.com/',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept "@name", "name" or a full link in the social fields, and links
     * without a scheme in the website field.
     */
    protected function prepareForValidation(): void
    {
        $links = [];

        foreach (Bio::LINKS as $field) {
            $value = trim((string) $this->input($field));

            if ($value === '') {
                $links[$field] = null;
            } elseif (isset(self::PROFILE_URLS[$field])
                && (str_starts_with($value, '@') || preg_match('/^[A-Za-z0-9_]{2,}$/', $value))) {
                $links[$field] = self::PROFILE_URLS[$field] . ltrim($value, '@');
            } else {
                $links[$field] = normalize_url($value);
            }
        }

        $this->merge($links);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'], // kaltas.description is VARCHAR(255)
            'avatar_icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'style' => ['nullable', Rule::in(Bio::THEMES)],
        ];

        foreach (Bio::LINKS as $field) {
            $rules[$field] = ['nullable', 'url:http,https', 'max:255'];
        }

        return $rules;
    }
}
