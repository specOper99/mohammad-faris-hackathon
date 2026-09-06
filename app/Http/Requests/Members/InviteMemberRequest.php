<?php

namespace App\Http\Requests\Members;

use Illuminate\Foundation\Http\FormRequest;

final class InviteMemberRequest extends FormRequest
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
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'skill' => ['nullable', 'string', 'max:200'],
        ];
    }
}
