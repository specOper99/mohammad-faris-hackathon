<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ReplaceCriteriaRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['required', 'string'],
            'items.*.nameEn' => ['required', 'string'],
            'items.*.nameAr' => ['required', 'string'],
            'items.*.weight' => ['required', 'numeric'],
            'items.*.minScore' => ['nullable', 'integer'],
            'items.*.maxScore' => ['nullable', 'integer'],
            'items.*.sortOrder' => ['nullable', 'integer'],
            'items.*.isActive' => ['nullable', 'boolean'],
        ];
    }
}
