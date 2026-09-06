<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTrackRequest extends FormRequest
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
            'nameEn' => ['sometimes', 'string'],
            'nameAr' => ['sometimes', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'difficulty' => ['sometimes', 'string'],
        ];
    }
}
