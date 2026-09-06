<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTrackRequest extends FormRequest
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
            'code' => ['required', 'size:1', 'unique:tracks,code'],
            'nameEn' => ['required', 'string'],
            'nameAr' => ['required', 'string'],
            'difficulty' => ['required', 'string'],
            'focusEn' => ['nullable', 'string'],
            'focusAr' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
