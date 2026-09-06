<?php

namespace App\Http\Requests\Submissions;

use Illuminate\Foundation\Http\FormRequest;

final class InitiateUploadRequest extends FormRequest
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
            'fileType' => ['required', 'string'],
            'fileName' => ['required', 'string', 'max:200'],
            'mimeType' => ['required', 'string'],
            'sizeBytes' => ['required', 'integer', 'min:1'],
            'partSizeBytes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
