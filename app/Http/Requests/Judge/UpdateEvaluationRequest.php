<?php

namespace App\Http\Requests\Judge;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateEvaluationRequest extends FormRequest
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
            'comments' => ['nullable', 'string'],
            'version' => ['nullable', 'integer'],
            'scores' => ['array'],
            'scores.*.criterionId' => ['required', 'uuid'],
            'scores.*.score' => ['required', 'integer'],
        ];
    }
}
