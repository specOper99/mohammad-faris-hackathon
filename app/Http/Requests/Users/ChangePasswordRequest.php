<?php

namespace App\Http\Requests\Users;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
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
            'current' => ['required', 'string'],
            'new' => array_merge(PasswordRules::rules(), ['same:confirm']),
            'confirm' => ['required'],
        ];
    }
}
