<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
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
            'registrationEnabled' => ['sometimes', 'boolean'],
            'registrationStart' => ['sometimes', 'date'],
            'registrationEnd' => ['sometimes', 'date'],
            'submissionEnabled' => ['sometimes', 'boolean'],
            'submissionStart' => ['sometimes', 'date'],
            'submissionEnd' => ['sometimes', 'date'],
            'scoringEnabled' => ['sometimes', 'boolean'],
            'publishResults' => ['sometimes', 'boolean'],
            'maxTeamMembers' => ['sometimes', 'integer', 'min:2', 'max:20'],
            'allowMultipleTeams' => ['sometimes', 'boolean'],
            'requireAdminTeamConfirmation' => ['sometimes', 'boolean'],
            'lockOnSubmit' => ['sometimes', 'boolean'],
            'lockOnDeadline' => ['sometimes', 'boolean'],
            'allMembersCanEdit' => ['sometimes', 'boolean'],
            'emailAllMembersOnSubmit' => ['sometimes', 'boolean'],
            'scoreAggregation' => ['sometimes', 'string'],
            'currentRulesVersion' => ['sometimes', 'string'],
            'currentDataUsageVersion' => ['sometimes', 'string'],
            'allowedTrackIds' => ['sometimes', 'array'],
            'filePolicy' => ['sometimes', 'array'],
            'requiredFileTypesOnSubmit' => ['sometimes', 'array'],
            'requiredFieldsOnSubmit' => ['sometimes', 'array'],
            'requiredFileTypesByTrackCode' => ['sometimes', 'array'],
            'allowDirectUploadBelowBytes' => ['sometimes', 'integer', 'min:0'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
