<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Administration\AdminMutationsAction;
use App\Actions\Administration\AssignJudgeAction;
use App\Actions\Administration\ChangeSubmissionStatusAction;
use App\Actions\Administration\CreateJudgeAction;
use App\Actions\Administration\DashboardAction;
use App\Actions\Administration\ReopenEvaluationAction;
use App\Actions\Administration\UnassignJudgeAction;
use App\Actions\Judging\RecomputeAggregationAction;
use App\Actions\Members\InviteMemberAction;
use App\Domain\Scoring\WeightSumValidator;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Enums\SubmissionStatus;
use App\Enums\TeamStatus;
use App\Exports\EvaluationsExport;
use App\Exports\SubmissionsExport;
use App\Exports\TeamsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignJudgeRequest;
use App\Http\Requests\Admin\CreateJudgeRequest;
use App\Http\Requests\Admin\ReopenSubmissionRequest;
use App\Http\Requests\Admin\ReplaceCriteriaRequest;
use App\Http\Requests\Admin\SetUserActiveRequest;
use App\Http\Requests\Admin\StoreTrackRequest;
use App\Http\Requests\Admin\TransferLeadershipRequest;
use App\Http\Requests\Admin\UpdateAdminTeamRequest;
use App\Http\Requests\Admin\UpdateJudgeRequest;
use App\Http\Requests\Admin\UpdatePublicationRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Http\Requests\Admin\UpdateSubmissionStatusRequest;
use App\Http\Requests\Admin\UpdateTeamStatusRequest;
use App\Http\Requests\Admin\UpdateTrackRequest;
use App\Http\Requests\Members\InviteMemberRequest;
use App\Http\Resources\SubmissionResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\TrackResource;
use App\Models\AuditLog;
use App\Models\ChallengeSettings;
use App\Models\Evaluation;
use App\Models\JudgeProfile;
use App\Models\ScoringCriterion;
use App\Models\Submission;
use App\Models\Team;
use App\Models\Track;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\CurrentUser;
use App\Support\Pagination;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Admin', weight: 8)]
final class AdminController extends Controller
{
    public function dashboard(DashboardAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute());
    }

    public function teams(Request $request): JsonResponse
    {
        $q = $this->teamQuery($request);
        $page = Pagination::page($request);
        $size = Pagination::pageSize($request);
        $total = $q->count();
        $items = $q->with(['leader', 'track'])->forPage($page, $size)->get()
            ->map(fn (Team $t) => (new TeamResource($t))->resolve($request))
            ->all();

        return ApiResponse::paged($items, $page, $size, $total);
    }

    public function team(string $id, Request $request): JsonResponse
    {
        $team = Team::query()->with(['members.user', 'track', 'leader', 'submission.files'])->findOrFail($id);

        return ApiResponse::success((new TeamResource($team))->resolve($request));
    }

    public function updateTeam(UpdateAdminTeamRequest $request, string $id): JsonResponse
    {
        $team = Team::query()->findOrFail($id);
        $data = $request->validated();
        $team->fill([
            'name' => $data['name'] ?? $team->name,
            'university' => $data['university'] ?? $team->university,
            'city' => $data['city'] ?? $team->city,
        ]);
        $team->save();

        return ApiResponse::success((new TeamResource($team->fresh(['members.user', 'track', 'leader'])))->resolve($request));
    }

    public function teamStatus(UpdateTeamStatusRequest $request, string $id, AdminMutationsAction $action): JsonResponse
    {
        $data = $request->validated();
        $team = Team::query()->findOrFail($id);
        $status = TeamStatus::from($data['status']);
        $action->updateTeamStatus($team, $status, $data['reason'] ?? null);

        return ApiResponse::success(['id' => $team->id, 'status' => $team->status->value]);
    }

    public function transferLeadership(TransferLeadershipRequest $request, string $id, AdminMutationsAction $action): JsonResponse
    {
        $team = Team::query()->findOrFail($id);
        $action->transferLeadership($team, (string) $request->validated('newLeaderUserId'));

        return ApiResponse::success(['ok' => true]);
    }

    public function inviteMember(InviteMemberRequest $request, string $id, InviteMemberAction $invite): JsonResponse
    {
        $team = Team::query()->findOrFail($id);
        $leader = $team->leader;
        $member = $invite->execute($leader, $request->validated());

        return ApiResponse::created(['id' => $member->id]);
    }

    public function users(Request $request): JsonResponse
    {
        $page = Pagination::page($request);
        $size = Pagination::pageSize($request);
        $q = User::query()->withDeleted();
        if ($search = $request->query('q')) {
            $like = '%'.Pagination::escapeLike((string) $search).'%';
            $q->where(fn ($b) => $b->where('email', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like));
        }
        $total = $q->count();
        $items = $q->orderBy('created_at')->forPage($page, $size)->get()->map(fn (User $u) => [
            'id' => $u->id,
            'email' => $u->email,
            'firstName' => $u->first_name,
            'lastName' => $u->last_name,
            'isActive' => $u->is_active,
            'roles' => $u->getRoleNames(),
        ])->all();

        return ApiResponse::paged($items, $page, $size, $total);
    }

    public function setActive(SetUserActiveRequest $request, string $id): JsonResponse
    {
        $user = User::query()->withDeleted()->findOrFail($id);
        $user->is_active = $request->boolean('isActive');
        $user->save();

        return ApiResponse::success(['id' => $user->id, 'isActive' => $user->is_active]);
    }

    #[Endpoint(description: 'Delete all sessions for the user. No request body.')]
    public function forceLogout(string $id): JsonResponse
    {
        DB::table('sessions')->where('user_id', $id)->delete();

        return ApiResponse::success(['ok' => true]);
    }

    public function createJudge(CreateJudgeRequest $request, CreateJudgeAction $action): JsonResponse
    {
        $profile = $action->execute(CurrentUser::require(), $request->validated());

        return ApiResponse::created(['id' => $profile->id, 'userId' => $profile->user_id]);
    }

    public function showJudge(string $id): JsonResponse
    {
        $p = JudgeProfile::query()->with('user')->findOrFail($id);

        return ApiResponse::success([
            'id' => $p->id,
            'email' => $p->user?->email,
            'firstName' => $p->user?->first_name,
            'lastName' => $p->user?->last_name,
            'specialization' => $p->specialization,
            'isActive' => $p->is_active,
        ]);
    }

    public function updateJudge(UpdateJudgeRequest $request, string $id): JsonResponse
    {
        $p = JudgeProfile::query()->findOrFail($id);
        $data = $request->validated();
        if (array_key_exists('specialization', $data)) {
            $p->specialization = $data['specialization'];
        }
        if (array_key_exists('isActive', $data)) {
            $p->is_active = (bool) $data['isActive'];
        }
        $p->save();

        return ApiResponse::success(['id' => $p->id, 'isActive' => $p->is_active]);
    }

    public function assign(AssignJudgeRequest $request, AssignJudgeAction $action): JsonResponse
    {
        $a = $action->execute(CurrentUser::require(), $request->validated());

        return ApiResponse::created(['id' => $a->id]);
    }

    public function unassign(string $id, UnassignJudgeAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $id);

        return ApiResponse::success(['ok' => true]);
    }

    public function submissions(Request $request): JsonResponse
    {
        $q = Submission::query()->with(['team', 'track']);
        $this->filterSubmission($q, $request);
        $page = Pagination::page($request);
        $size = Pagination::pageSize($request);
        $total = $q->count();
        $items = $q->orderByDesc('created_at')->forPage($page, $size)->get()->map(fn (Submission $s) => [
            'id' => $s->id,
            'submissionCode' => $s->submission_code,
            'teamCode' => $s->team?->team_code,
            'projectName' => $s->project_name,
            'status' => $s->status->value,
            'submittedAt' => optional($s->submitted_at)?->toIso8601ZuluString(),
        ])->all();

        return ApiResponse::paged($items, $page, $size, $total);
    }

    public function showSubmission(string $id, Request $request): JsonResponse
    {
        $s = Submission::query()->with(['team', 'track', 'currentFiles', 'evaluations.scores'])->findOrFail($id);

        return ApiResponse::success((new SubmissionResource($s))->resolve($request));
    }

    public function submissionStatus(UpdateSubmissionStatusRequest $request, string $id, ChangeSubmissionStatusAction $action): JsonResponse
    {
        $s = Submission::query()->findOrFail($id);
        $action->execute(CurrentUser::require(), $s, SubmissionStatus::from((string) $request->validated('status')));

        return ApiResponse::success(['id' => $s->id, 'status' => $s->fresh()->status->value]);
    }

    public function reopenSubmission(ReopenSubmissionRequest $request, string $id, ChangeSubmissionStatusAction $action): JsonResponse
    {
        $s = Submission::query()->findOrFail($id);
        $unlockUntil = $request->validated('unlockUntil');
        $action->reopen(CurrentUser::require(), $s, is_string($unlockUntil) ? $unlockUntil : null);

        return ApiResponse::success(['id' => $s->id, 'status' => $s->status->value, 'reopenCount' => $s->reopen_count]);
    }

    public function publication(UpdatePublicationRequest $request, string $id): JsonResponse
    {
        $s = Submission::query()->findOrFail($id);
        $s->publication_status = PublicationStatus::from((string) $request->validated('publicationStatus'));
        $s->save();

        return ApiResponse::success(['id' => $s->id, 'publicationStatus' => $s->publication_status->value]);
    }

    public function evaluations(Request $request): JsonResponse
    {
        $q = Evaluation::query()->with(['submission.team', 'judgeProfile.user']);
        $page = Pagination::page($request);
        $size = Pagination::pageSize($request);
        $total = $q->count();
        $items = $q->orderByDesc('updated_at')->forPage($page, $size)->get()->map(fn (Evaluation $e) => [
            'id' => $e->id,
            'submissionCode' => $e->submission?->submission_code,
            'teamCode' => $e->submission?->team?->team_code,
            'judgeEmail' => $e->judgeProfile?->user?->email,
            'status' => $e->status->value,
            'totalScore' => $e->total_score,
        ])->all();

        return ApiResponse::paged($items, $page, $size, $total);
    }

    #[Endpoint(description: 'Return a submitted evaluation to draft. No request body.')]
    public function reopenEvaluation(string $id, ReopenEvaluationAction $action): JsonResponse
    {
        $e = Evaluation::query()->findOrFail($id);
        $action->execute(CurrentUser::require(), $e);

        return ApiResponse::success(['id' => $e->id, 'status' => $e->status->value]);
    }

    public function aggregation(string $submissionId, RecomputeAggregationAction $recompute): JsonResponse
    {
        $s = Submission::query()->findOrFail($submissionId);
        $recompute->execute($s);

        return ApiResponse::success(['submissionId' => $s->id, 'aggregatedScore' => $s->fresh()->aggregated_score]);
    }

    public function storeTrack(StoreTrackRequest $request): JsonResponse
    {
        $data = $request->validated();
        $track = Track::query()->create([
            'code' => strtoupper($data['code']),
            'name_en' => $data['nameEn'],
            'name_ar' => $data['nameAr'],
            'difficulty' => $data['difficulty'],
            'focus_en' => $data['focusEn'] ?? null,
            'focus_ar' => $data['focusAr'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'sort_order' => Track::query()->max('sort_order') + 1,
        ]);

        return ApiResponse::created((new TrackResource($track))->resolve());
    }

    public function updateTrack(UpdateTrackRequest $request, string $id): JsonResponse
    {
        $track = Track::query()->findOrFail($id);
        $data = $request->validated();
        $track->fill([
            'name_en' => $data['nameEn'] ?? $track->name_en,
            'name_ar' => $data['nameAr'] ?? $track->name_ar,
            'is_active' => array_key_exists('isActive', $data) ? (bool) $data['isActive'] : $track->is_active,
            'difficulty' => $data['difficulty'] ?? $track->difficulty,
        ]);
        $track->save();

        return ApiResponse::success((new TrackResource($track))->resolve());
    }

    public function criteria(): JsonResponse
    {
        return ApiResponse::success(ScoringCriterion::query()->orderBy('sort_order')->get()->map(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'nameEn' => $c->name_en,
            'nameAr' => $c->name_ar,
            'weight' => (float) $c->weight,
            'minScore' => $c->min_score,
            'maxScore' => $c->max_score,
            'isActive' => $c->is_active,
        ])->all());
    }

    public function replaceCriteria(ReplaceCriteriaRequest $request, AdminMutationsAction $action, WeightSumValidator $validator): JsonResponse
    {
        /** @var list<array<string, mixed>> $items */
        $items = $request->validated('items');
        $action->replaceCriteria($items, $validator);

        return $this->criteria();
    }

    public function settings(): JsonResponse
    {
        $s = ChallengeSettings::current();

        return ApiResponse::success([
            'id' => $s->id,
            'version' => $s->version,
            'challengeYear' => $s->challenge_year,
            'registrationEnabled' => $s->registration_enabled,
            'registrationStart' => $s->registration_start->toIso8601ZuluString(),
            'registrationEnd' => $s->registration_end->toIso8601ZuluString(),
            'submissionEnabled' => $s->submission_enabled,
            'submissionStart' => $s->submission_start->toIso8601ZuluString(),
            'submissionEnd' => $s->submission_end->toIso8601ZuluString(),
            'scoringEnabled' => $s->scoring_enabled,
            'publishResults' => $s->publish_results,
            'maxTeamMembers' => $s->max_team_members,
            'allowMultipleTeams' => $s->allow_multiple_teams,
            'requireAdminTeamConfirmation' => $s->require_admin_team_confirmation,
            'currentRulesVersion' => $s->current_rules_version,
            'currentDataUsageVersion' => $s->current_data_usage_version,
            'requiredFileTypesOnSubmit' => $s->required_file_types_on_submit,
            'requiredFieldsOnSubmit' => $s->required_fields_on_submit,
        ]);
    }

    public function updateSettings(UpdateSettingsRequest $request, AdminMutationsAction $action): JsonResponse
    {
        $s = $action->updateSettings(ChallengeSettings::current(), $request->validated(), CurrentUser::require());

        return ApiResponse::success(['id' => $s->id, 'version' => $s->version]);
    }

    public function exportTeams(Request $request, AuditLogger $audit): BinaryFileResponse
    {
        $q = $this->teamQuery($request);
        if (! $q->exists()) {
            throw AppException::code('EXPORT_EMPTY', 404);
        }
        $audit->write(AuditAction::EXPORT_TEAMS, null);
        $ext = str_ends_with($request->path(), '.xlsx') ? 'xlsx' : 'csv';

        return Excel::download(new TeamsExport($q), 'teams.'.$ext, $ext === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV);
    }

    public function exportSubmissions(Request $request, AuditLogger $audit): BinaryFileResponse
    {
        $q = Submission::query();
        $this->filterSubmission($q, $request);
        if (! $q->exists()) {
            throw AppException::code('EXPORT_EMPTY', 404);
        }
        $audit->write(AuditAction::EXPORT_SUBMISSIONS, null);
        $ext = str_ends_with($request->path(), '.xlsx') ? 'xlsx' : 'csv';

        return Excel::download(new SubmissionsExport($q), 'submissions.'.$ext, $ext === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV);
    }

    public function exportEvaluations(Request $request, AuditLogger $audit): BinaryFileResponse
    {
        $q = Evaluation::query();
        if (! $q->exists()) {
            throw AppException::code('EXPORT_EMPTY', 404);
        }
        $audit->write(AuditAction::EXPORT_EVALUATIONS, null);
        $ext = str_ends_with($request->path(), '.xlsx') ? 'xlsx' : 'csv';

        return Excel::download(new EvaluationsExport($q), 'evaluations.'.$ext, $ext === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $page = Pagination::page($request);
        $size = Pagination::pageSize($request);
        $q = AuditLog::query()->orderByDesc('created_at');
        $total = $q->count();
        $items = $q->forPage($page, $size)->get()->map(fn (AuditLog $l) => [
            'id' => $l->id,
            'action' => $l->action,
            'entityType' => $l->entity_type,
            'entityId' => $l->entity_id,
            'userId' => $l->user_id,
            'createdAt' => optional($l->created_at)?->toIso8601ZuluString(),
        ])->all();

        return ApiResponse::paged($items, $page, $size, $total);
    }

    /**
     * @return Builder<Team>
     */
    private function teamQuery(Request $request): Builder
    {
        $q = Team::query();
        if ($search = $request->query('q')) {
            $like = '%'.Pagination::escapeLike((string) $search).'%';
            $q->where(function ($b) use ($like): void {
                $b->where('name', 'like', $like)
                    ->orWhere('team_code', 'like', $like)
                    ->orWhereHas('leader', fn ($l) => $l->where('email', 'like', $like));
            });
        }
        if ($request->filled('trackId')) {
            $q->where('track_id', $request->query('trackId'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        if ($request->filled('city')) {
            $q->where('city', $request->query('city'));
        }
        if ($request->filled('university')) {
            $q->where('university', $request->query('university'));
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->query('to'));
        }

        return $q->orderByDesc('created_at');
    }

    /**
     * @param  Builder<Submission>  $q
     */
    private function filterSubmission(Builder $q, Request $request): void
    {
        if ($search = $request->query('q')) {
            $like = '%'.Pagination::escapeLike((string) $search).'%';
            $q->where(function ($b) use ($like): void {
                $b->where('submission_code', 'like', $like)->orWhere('project_name', 'like', $like);
            });
        }
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        if ($request->filled('trackId')) {
            $q->where('track_id', $request->query('trackId'));
        }
    }
}
