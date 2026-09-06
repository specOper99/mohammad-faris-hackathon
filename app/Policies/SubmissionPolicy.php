<?php

namespace App\Policies;

use App\Enums\TeamMemberStatus;
use App\Models\JudgeAssignment;
use App\Models\Submission;
use App\Models\User;

final class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('judge') && $user->judgeProfile) {
            return JudgeAssignment::query()
                ->where('judge_profile_id', $user->judgeProfile->id)
                ->where('submission_id', $submission->id)
                ->whereNull('unassigned_at')
                ->exists();
        }

        return $submission->team->members()
            ->where('user_id', $user->id)
            ->where('status', TeamMemberStatus::Active)
            ->exists();
    }

    public function update(User $user, Submission $submission): bool
    {
        return app(TeamPolicy::class)->update($user, $submission->team);
    }

    public function submit(User $user, Submission $submission): bool
    {
        return $this->update($user, $submission);
    }
}
