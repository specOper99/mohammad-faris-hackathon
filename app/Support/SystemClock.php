<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return now()->toImmutable();
    }
}
