<?php

namespace App\Policies;

use App\Models\ChallengeSettings;
use App\Models\Evaluation;
use App\Models\JudgeAssignment;
use App\Models\User;

final class EvaluationPolicy
{
    public function score(User $user, Evaluation $evaluation): bool
    {
        if (! $user->hasRole('judge') || $user->judgeProfile === null || ! $user->judgeProfile->is_active) {
            return false;
        }
        if (! ChallengeSettings::current()->scoring_enabled) {
            return false;
        }

        return JudgeAssignment::query()
            ->where('judge_profile_id', $user->judgeProfile->id)
            ->where('submission_id', $evaluation->submission_id)
            ->whereNull('unassigned_at')
            ->exists();
    }
}
