<?php

namespace App\Actions\Administration;

use App\Domain\Submissions\SubmissionStateMachine;
use App\Enums\AuditAction;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\Finalist;
use App\Notifications\UnderReview;
use App\Support\AuditLogger;
use App\Support\OutboxNotifier;

final class ChangeSubmissionStatusAction
{
    public function __construct(
        private SubmissionStateMachine $machine,
        private AuditLogger $audit,
        private OutboxNotifier $outbox,
    ) {}

    public function execute(User $admin, Submission $submission, SubmissionStatus $to): Submission
    {
        $from = $submission->status;
        $this->machine->assertCanTransition($from, $to);
        $submission->status = $to;
        $submission->save();
        $this->audit->write(AuditAction::SUBMISSION_UPDATE, $submission, ['status' => $from->value], ['status' => $to->value], $admin);

        $leader = $submission->team?->leader;
        if ($to === SubmissionStatus::UnderReview && $leader !== null) {
            $this->outbox->send($leader, new UnderReview((string) $submission->submission_code));
        }
        if ($to === SubmissionStatus::Finalist && $leader !== null) {
            $this->outbox->send($leader, new Finalist((string) $submission->submission_code));
        }

        return $submission;
    }

    public function reopen(User $admin, Submission $submission, ?string $unlockUntil = null): Submission
    {
        $from = $submission->status;
        if (! in_array($from, [SubmissionStatus::Submitted, SubmissionStatus::Rejected], true)) {
            $this->machine->assertCanTransition($from, SubmissionStatus::Draft);
        }
        $submission->status = SubmissionStatus::Draft;
        $submission->locked_at = null;
        $submission->lock_reason = null;
        $submission->reopen_count++;
        $submission->reopened_at = now()->toImmutable();
        $submission->reopened_by_user_id = $admin->id;
        $submission->save();
        $this->audit->write(AuditAction::SUBMISSION_REOPEN, $submission, ['status' => $from->value], ['status' => 'draft'], $admin);

        return $submission;
    }
}
