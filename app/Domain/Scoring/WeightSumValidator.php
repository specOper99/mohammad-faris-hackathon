<?php

namespace App\Domain\Scoring;

use App\Support\AppException;

final class WeightSumValidator
{
    /**
     * @param  list<float|int|string>  $weights
     */
    public function assert(array $weights, float $tolerance = 0.0001): void
    {
        $sum = 0.0;
        foreach ($weights as $weight) {
            $value = (float) $weight;
            if ($value <= 0) {
                throw AppException::code('SCORING_WEIGHTS_INVALID', 422);
            }
            $sum += $value;
        }

        if (abs($sum - 1.0) > $tolerance) {
            throw AppException::code('CRITERIA_WEIGHT_SUM', 422);
        }
    }
}
