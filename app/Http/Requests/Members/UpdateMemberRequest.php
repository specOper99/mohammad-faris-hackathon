<?php

namespace App\Http\Requests\Members;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMemberRequest extends FormRequest
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
            'skill' => ['nullable', 'string', 'max:200'],
        ];
    }
}
