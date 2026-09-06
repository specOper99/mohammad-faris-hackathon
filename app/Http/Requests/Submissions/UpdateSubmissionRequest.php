<?php

namespace App\Http\Requests\Submissions;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSubmissionRequest extends FormRequest
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
            'projectName' => ['nullable', 'string', 'max:200'],
            'abstract' => ['nullable', 'string'],
            'problemDescription' => ['nullable', 'string'],
            'solutionDescription' => ['nullable', 'string'],
            'githubUrl' => ['nullable', 'url', 'max:500'],
            'demoUrl' => ['nullable', 'url', 'max:500'],
            'limitations' => ['nullable', 'string'],
            'aiUsage' => ['nullable', 'string'],
            'readmeInline' => ['nullable', 'string'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
