<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CreateJudgeRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'firstName' => ['required', 'string'],
            'lastName' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:10'],
            'specialization' => ['nullable', 'string'],
            'sendInviteEmail' => ['sometimes', 'boolean'],
        ];
    }
}
