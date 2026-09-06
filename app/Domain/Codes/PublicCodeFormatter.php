<?php

namespace App\Domain\Codes;

final class PublicCodeFormatter
{
    public function team(int $year, int $sequence): string
    {
        return sprintf('%s-%d-%05d', config('exoplanet.team_code_prefix', 'EXP'), $year, $sequence);
    }

    public function submission(int $year, int $sequence): string
    {
        return sprintf('%s-%d-%05d', config('exoplanet.submission_code_prefix', 'SUB'), $year, $sequence);
    }
}
