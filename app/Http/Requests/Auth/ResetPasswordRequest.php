<?php

namespace App\Http\Requests\Auth;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'password' => array_merge(PasswordRules::rules(), ['same:confirmPassword']),
            'confirmPassword' => ['required'],
        ];
    }
}
