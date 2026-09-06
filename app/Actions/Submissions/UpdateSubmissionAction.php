<?php

namespace App\Actions\Submissions;

use App\Enums\LockReason;
use App\Enums\SubmissionStatus;
use App\Models\ChallengeSettings;
use App\Models\Submission;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Support\AppException;
use App\Support\Clock;
use App\Support\HttpsUrl;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class UpdateSubmissionAction
{
    public function __construct(private Clock $clock) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Submission $submission, array $data): Submission
    {
        if (! app(SubmissionPolicy::class)->update($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        $this->assertMutable($submission);

        $fields = [
            'project_name' => $data['projectName'] ?? null,
            'abstract' => $data['abstract'] ?? null,
            'problem_description' => $data['problemDescription'] ?? null,
            'solution_description' => $data['solutionDescription'] ?? null,
            'github_url' => $data['githubUrl'] ?? null,
            'demo_url' => $data['demoUrl'] ?? null,
            'limitations' => $data['limitations'] ?? null,
            'ai_usage' => $data['aiUsage'] ?? null,
            'readme_inline' => $data['readmeInline'] ?? null,
            'updated_by_user_id' => $user->id,
        ];

        foreach (['github_url', 'demo_url'] as $col) {
            if (! empty($fields[$col]) && ! HttpsUrl::isValid($fields[$col])) {
                throw AppException::code('VALIDATION_FAILED', 422, [$col => ['HTTPS URL required.']]);
            }
        }

        $submission->saveWithVersion($fields, isset($data['version']) ? (int) $data['version'] : $submission->version);

        return $submission;
    }

    public function assertMutable(Submission $submission): void
    {
        $settings = ChallengeSettings::current();
        $now = $this->clock->now();
        if ($settings->lock_on_deadline && $now->gt($settings->submission_end) && $submission->locked_at === null) {
            $submission->locked_at = $now;
            $submission->lock_reason = LockReason::Deadline;
            $submission->save();
        }
        if ($submission->locked_at !== null) {
            throw AppException::code('SUBMISSION_LOCKED', 409);
        }
        if ($submission->status !== SubmissionStatus::Draft) {
            throw AppException::code('SUBMISSION_NOT_DRAFT', 409);
        }
    }
}
