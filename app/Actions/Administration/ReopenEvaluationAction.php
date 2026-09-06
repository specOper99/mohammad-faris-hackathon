<?php

namespace App\Actions\Administration;

use App\Actions\Judging\RecomputeAggregationAction;
use App\Enums\AuditAction;
use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\User;
use App\Support\AppException;
use App\Support\AuditLogger;

final class ReopenEvaluationAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(User $admin, Evaluation $evaluation): Evaluation
    {
        $evaluation->status = EvaluationStatus::Reopened;
        $evaluation->reopened_at = now()->toImmutable();
        $evaluation->reopened_by_user_id = $admin->id;
        $evaluation->save();
        $submission = $evaluation->submission;
        if ($submission === null) {
            throw AppException::code('NOT_FOUND', 404);
        }
        app(RecomputeAggregationAction::class)->execute($submission);
        $this->audit->write(AuditAction::EVALUATION_REOPEN, $evaluation, null, null, $admin);

        return $evaluation;
    }
}
