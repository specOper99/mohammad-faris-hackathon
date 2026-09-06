<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTeamRequest extends FormRequest
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
            'teamName' => ['sometimes', 'string', 'min:2', 'max:200'],
            'university' => ['nullable', 'string', 'max:200'],
            'organization' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'technicalLevel' => ['sometimes', 'in:Beginner,Intermediate,Advanced'],
            'githubUrl' => ['nullable', 'url', 'max:500'],
            'portfolioUrl' => ['nullable', 'url', 'max:500'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
