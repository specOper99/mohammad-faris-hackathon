<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AssignJudgeRequest extends FormRequest
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
            'judgeProfileId' => ['required', 'uuid'],
            'submissionId' => ['required', 'uuid'],
        ];
    }
}
