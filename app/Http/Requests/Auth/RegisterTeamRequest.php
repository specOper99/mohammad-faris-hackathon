<?php

namespace App\Http\Requests\Auth;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RegisterTeamRequest extends FormRequest
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
            'teamName' => ['required', 'string', 'min:2', 'max:200'],
            'university' => ['nullable', 'string', 'max:200'],
            'organization' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'technicalLevel' => ['required', 'in:Beginner,Intermediate,Advanced'],
            'trackId' => ['required', 'uuid', 'exists:tracks,id'],
            'githubUrl' => ['nullable', 'url', 'max:500'],
            'portfolioUrl' => ['nullable', 'url', 'max:500'],
            'acceptedRulesVersion' => ['required', 'string'],
            'acceptedDataUsageVersion' => ['required', 'string'],
            'leader.firstName' => ['required', 'string', 'max:100'],
            'leader.lastName' => ['required', 'string', 'max:100'],
            'leader.email' => ['required', 'email'],
            'leader.phone' => ['nullable', 'regex:/^\+[0-9]{8,15}$/'],
            'leader.password' => array_merge(PasswordRules::rules(), ['same:leader.confirmPassword']),
            'leader.confirmPassword' => ['required'],
            'leader.locale' => ['nullable', 'in:en,ar'],
            'members' => ['array', 'max:20'],
            'members.*.firstName' => ['required', 'string', 'max:100'],
            'members.*.lastName' => ['required', 'string', 'max:100'],
            'members.*.email' => ['required', 'email', 'different:leader.email'],
            'members.*.skill' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $emails = array_map(
                fn ($m) => strtolower((string) ($m['email'] ?? '')),
                $this->input('members', [])
            );
            if (count($emails) !== count(array_unique($emails))) {
                $validator->errors()->add('members', 'Member emails must be distinct.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toData(): array
    {
        return $this->validated();
    }
}
