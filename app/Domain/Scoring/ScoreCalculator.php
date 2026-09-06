<?php

namespace App\Domain\Scoring;

use App\Support\AppException;

final class ScoreCalculator
{
    /**
     * @param  list<array{code: string, weight: float, score: int, min?: int, max?: int}>  $items
     */
    public function total(array $items): float
    {
        if ($items === []) {
            throw AppException::code('SCORING_CRITERION_MISSING', 422);
        }

        $sum = 0.0;
        foreach ($items as $item) {
            $min = $item['min'] ?? 0;
            $max = $item['max'] ?? 100;
            if ($item['score'] < $min || $item['score'] > $max) {
                throw AppException::code('SCORING_SCORE_RANGE', 422);
            }
            $sum += $item['score'] * $item['weight'];
        }

        return round($sum, 2, PHP_ROUND_HALF_UP);
    }
}
