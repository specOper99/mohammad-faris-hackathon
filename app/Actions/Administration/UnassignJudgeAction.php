<?php

namespace App\Actions\Administration;

use App\Enums\AuditAction;
use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\JudgeAssignment;
use App\Models\User;
use App\Support\AppException;
use App\Support\AuditLogger;

final class UnassignJudgeAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(User $admin, string $assignmentId): void
    {
        $assignment = JudgeAssignment::query()->find($assignmentId);
        if ($assignment === null) {
            throw AppException::code('NOT_FOUND', 404);
        }

        $submitted = Evaluation::query()
            ->where('submission_id', $assignment->submission_id)
            ->where('judge_profile_id', $assignment->judge_profile_id)
            ->where('status', EvaluationStatus::Submitted)
            ->exists();
        if ($submitted) {
            throw AppException::code('ASSIGNMENT_HAS_EVALUATION', 409);
        }

        $assignment->unassigned_at = now()->toImmutable();
        $assignment->save();
        $this->audit->write(AuditAction::JUDGE_UNASSIGN, $assignment, null, null, $admin);
    }
}
