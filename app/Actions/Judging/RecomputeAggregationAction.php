<?php

namespace App\Actions\Judging;

use App\Enums\EvaluationStatus;
use App\Enums\ScoreAggregation;
use App\Models\ChallengeSettings;
use App\Models\Evaluation;
use App\Models\Submission;

final class RecomputeAggregationAction
{
    public function execute(Submission $submission): void
    {
        $settings = ChallengeSettings::current();
        $scores = Evaluation::query()
            ->where('submission_id', $submission->id)
            ->where('status', EvaluationStatus::Submitted)
            ->pluck('total_score')
            ->map(fn ($v) => (float) $v)
            ->values()
            ->all();

        if ($scores === []) {
            $submission->aggregated_score = null;
            $submission->save();

            return;
        }

        if ($settings->score_aggregation === ScoreAggregation::Median) {
            sort($scores);
            $count = count($scores);
            $mid = intdiv($count, 2);
            $value = $count % 2 === 1 ? $scores[$mid] : ($scores[$mid - 1] + $scores[$mid]) / 2;
        } else {
            $value = array_sum($scores) / count($scores);
        }

        $submission->aggregated_score = round($value, 2, PHP_ROUND_HALF_UP);
        $submission->save();
    }
}
