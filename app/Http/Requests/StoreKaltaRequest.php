<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKaltaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . upload_limit_kb(),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());

                    if (in_array($extension, config('kalta.blocked_extensions'), true)) {
                        $fail(__('This file type is not allowed.'));
                    }
                },
            ],
        ];
    }
}
