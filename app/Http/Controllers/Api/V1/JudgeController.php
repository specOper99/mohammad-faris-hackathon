<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Judging\UpsertEvaluationAction;
use App\Domain\Scoring\ScoreCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Judge\UpdateEvaluationRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Evaluation;
use App\Models\JudgeAssignment;
use App\Models\Submission;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\CurrentUser;
use App\Support\Pagination;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Judge', weight: 7)]
final class JudgeController extends Controller
{
    public function submissions(Request $request): JsonResponse
    {
        $user = CurrentUser::require();
        $profile = $user->judgeProfile;
        if ($profile === null) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        $page = Pagination::page($request);
        $size = Pagination::pageSize($request);
        $q = JudgeAssignment::query()
            ->with(['submission.track', 'submission.team'])
            ->where('judge_profile_id', $profile->id)
            ->whereNull('unassigned_at')
            ->orderByDesc('assigned_at');

        $total = $q->count();
        $items = $q->forPage($page, $size)->get()->map(function (JudgeAssignment $a) use ($profile) {
            $eval = Evaluation::query()
                ->where('submission_id', $a->submission_id)
                ->where('judge_profile_id', $profile->id)
                ->first();

            return [
                'id' => $a->submission_id,
                'submissionCode' => $a->submission?->submission_code,
                'projectName' => $a->submission?->project_name,
                'track' => $a->submission?->track?->code,
                'teamName' => $a->submission?->team?->name,
                'status' => $a->submission?->status->value,
                'assignedAt' => optional($a->assigned_at)?->toIso8601ZuluString(),
                'evaluationStatus' => $eval?->status->value,
                'totalScore' => $eval?->status->value === 'submitted' ? $eval->total_score : null,
            ];
        })->all();

        return ApiResponse::paged($items, $page, $size, $total);
    }

    public function show(string $id, UpsertEvaluationAction $guard): JsonResponse
    {
        $sub = Submission::query()->find($id);
        if ($sub === null) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }
        $guard->assignedProfile(CurrentUser::require(), $sub);

        return ApiResponse::success((new SubmissionResource($sub))->resolve(request()));
    }

    public function evaluation(string $id, UpsertEvaluationAction $guard): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $profile = $guard->assignedProfile(CurrentUser::require(), $sub);
        $eval = Evaluation::query()
            ->with('scores')
            ->where('submission_id', $sub->id)
            ->where('judge_profile_id', $profile->id)
            ->first();

        return ApiResponse::success($eval ? [
            'id' => $eval->id,
            'status' => $eval->status->value,
            'comments' => $eval->comments,
            'totalScore' => $eval->total_score,
            'version' => $eval->version,
            'scores' => $eval->scores->map(fn ($s) => [
                'criterionId' => $s->scoring_criterion_id,
                'score' => $s->score,
            ])->all(),
        ] : null);
    }

    public function updateEvaluation(UpdateEvaluationRequest $request, string $id, UpsertEvaluationAction $action): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $eval = $action->execute(CurrentUser::require(), $sub, $request->validated());

        return ApiResponse::success(['id' => $eval->id, 'status' => $eval->status->value, 'version' => $eval->version]);
    }

    #[Endpoint(description: 'Submit the evaluation. No request body.')]
    public function submitEvaluation(string $id, UpsertEvaluationAction $action, ScoreCalculator $calc, AuditLogger $audit): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $eval = $action->submit(CurrentUser::require(), $sub, $calc, $audit);

        return ApiResponse::success(['id' => $eval->id, 'totalScore' => $eval->total_score, 'status' => $eval->status->value]);
    }
}
