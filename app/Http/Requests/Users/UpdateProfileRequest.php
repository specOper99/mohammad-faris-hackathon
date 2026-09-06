<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['sometimes', 'string', 'max:100'],
            'lastName' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^\+[0-9]{8,15}$/'],
            'locale' => ['sometimes', 'in:en,ar'],
        ];
    }
}
