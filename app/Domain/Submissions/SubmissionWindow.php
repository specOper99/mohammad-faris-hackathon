<?php

namespace App\Domain\Submissions;

use Carbon\CarbonImmutable;

final class SubmissionWindow
{
    public function isOpen(
        CarbonImmutable $now,
        bool $enabled,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): bool {
        return $enabled && $now->gte($start) && $now->lte($end);
    }

    public function deadlinePassed(CarbonImmutable $now, CarbonImmutable $end): bool
    {
        return $now->gt($end);
    }
}
