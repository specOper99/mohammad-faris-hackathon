<?php

namespace App\Domain\Submissions;

use App\Enums\SubmissionStatus;
use App\Support\AppException;

final class SubmissionStateMachine
{
    /**
     * @return list<SubmissionStatus>
     */
    public function allowed(SubmissionStatus $from): array
    {
        return match ($from) {
            SubmissionStatus::Draft => [SubmissionStatus::Submitted],
            SubmissionStatus::Submitted => [
                SubmissionStatus::UnderReview,
                SubmissionStatus::Draft,
                SubmissionStatus::Rejected,
            ],
            SubmissionStatus::UnderReview => [
                SubmissionStatus::Judged,
                SubmissionStatus::Finalist,
                SubmissionStatus::Rejected,
            ],
            SubmissionStatus::Judged => [
                SubmissionStatus::Finalist,
                SubmissionStatus::Rejected,
            ],
            SubmissionStatus::Finalist => [
                SubmissionStatus::Judged,
                SubmissionStatus::Rejected,
            ],
            SubmissionStatus::Rejected => [SubmissionStatus::Draft],
        };
    }

    public function assertCanTransition(SubmissionStatus $from, SubmissionStatus $to): void
    {
        if (! in_array($to, $this->allowed($from), true)) {
            throw AppException::code('SUBMISSION_STATUS_ILLEGAL', 409);
        }
    }

    public function isAdminReopen(SubmissionStatus $from, SubmissionStatus $to): bool
    {
        return $to === SubmissionStatus::Draft
            && in_array($from, [SubmissionStatus::Submitted, SubmissionStatus::Rejected], true);
    }
}
