<?php

namespace App\Domain\Registration;

use Carbon\CarbonImmutable;

final class RegistrationWindow
{
    public function canRegister(
        CarbonImmutable $now,
        bool $enabled,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): bool {
        return $enabled && $now->gte($start) && $now->lte($end);
    }

    public function notStarted(CarbonImmutable $now, CarbonImmutable $start): bool
    {
        return $now->lt($start);
    }
}
