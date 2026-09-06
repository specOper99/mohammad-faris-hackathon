<?php

namespace App\Http\Requests\Submissions;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteUploadRequest extends FormRequest
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
            'parts' => ['nullable', 'array'],
            'parts.*.etag' => ['required_with:parts', 'string'],
            'parts.*.partNumber' => ['required_with:parts', 'integer', 'min:1'],
        ];
    }
}
