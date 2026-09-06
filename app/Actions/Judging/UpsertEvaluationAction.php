<?php

namespace App\Actions\Judging;

use App\Domain\Scoring\ScoreCalculator;
use App\Enums\AuditAction;
use App\Enums\EvaluationStatus;
use App\Models\ChallengeSettings;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\JudgeAssignment;
use App\Models\JudgeProfile;
use App\Models\ScoringCriterion;
use App\Models\Submission;
use App\Models\User;
use App\Support\AppException;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class UpsertEvaluationAction
{
    public function assignedProfile(User $user, Submission $submission): JudgeProfile
    {
        $profile = $user->judgeProfile;
        if ($profile === null || ! $profile->is_active) {
            throw AppException::code('JUDGE_INACTIVE', 403);
        }
        $assigned = JudgeAssignment::query()
            ->where('judge_profile_id', $profile->id)
            ->where('submission_id', $submission->id)
            ->whereNull('unassigned_at')
            ->exists();
        if (! $assigned) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Submission $submission, array $data): Evaluation
    {
        if (! ChallengeSettings::current()->scoring_enabled) {
            throw AppException::code('SCORING_DISABLED', 409);
        }
        $profile = $this->assignedProfile($user, $submission);

        $evaluation = Evaluation::query()->firstOrCreate(
            ['submission_id' => $submission->id, 'judge_profile_id' => $profile->id],
            ['status' => EvaluationStatus::Draft, 'version' => 1],
        );

        if ($evaluation->status === EvaluationStatus::Submitted) {
            throw AppException::code('EVALUATION_IMMUTABLE', 409);
        }

        $evaluation->comments = $data['comments'] ?? $evaluation->comments;
        $evaluation->status = EvaluationStatus::Draft;
        $evaluation->saveWithVersion([], isset($data['version']) ? (int) $data['version'] : $evaluation->version);

        foreach ($data['scores'] ?? [] as $row) {
            EvaluationScore::query()->updateOrCreate(
                [
                    'evaluation_id' => $evaluation->id,
                    'scoring_criterion_id' => $row['criterionId'],
                ],
                ['score' => (int) $row['score']],
            );
        }

        return $evaluation->fresh('scores');
    }

    public function submit(User $user, Submission $submission, ScoreCalculator $calc, AuditLogger $audit): Evaluation
    {
        if (! ChallengeSettings::current()->scoring_enabled) {
            throw AppException::code('SCORING_DISABLED', 409);
        }
        $profile = $this->assignedProfile($user, $submission);

        return DB::transaction(function () use ($profile, $submission, $calc, $audit, $user) {
            $evaluation = Evaluation::query()
                ->where('submission_id', $submission->id)
                ->where('judge_profile_id', $profile->id)
                ->lockForUpdate()
                ->first();
            if ($evaluation === null) {
                throw AppException::code('NOT_FOUND', 404);
            }
            if ($evaluation->status === EvaluationStatus::Submitted) {
                throw AppException::code('EVALUATION_IMMUTABLE', 409);
            }

            $criteria = ScoringCriterion::query()->where('is_active', true)->orderBy('sort_order')->get();
            $scores = $evaluation->scores()->get()->keyBy('scoring_criterion_id');
            $items = [];
            foreach ($criteria as $criterion) {
                $score = $scores->get($criterion->id);
                if ($score === null) {
                    throw AppException::code('SCORING_CRITERION_MISSING', 422);
                }
                $items[] = [
                    'code' => $criterion->code,
                    'weight' => (float) $criterion->weight,
                    'score' => (int) $score->score,
                    'min' => (int) $criterion->min_score,
                    'max' => (int) $criterion->max_score,
                ];
            }

            $evaluation->total_score = $calc->total($items);
            $evaluation->status = EvaluationStatus::Submitted;
            $evaluation->submitted_at = now()->toImmutable();
            $evaluation->save();

            $fresh = $submission->fresh();
            if ($fresh !== null) {
                app(RecomputeAggregationAction::class)->execute($fresh);
            }
            $audit->write(AuditAction::EVALUATION_SUBMIT, $evaluation, null, ['total' => $evaluation->total_score], $user);

            return $evaluation;
        });
    }
}
