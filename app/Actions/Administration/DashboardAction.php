<?php

namespace App\Actions\Administration;

use App\Enums\EvaluationStatus;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Track;
use App\Models\User;

final class DashboardAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $counts = Team::query()
            ->selectRaw('track_id, count(*) as c')
            ->groupBy('track_id')
            ->get();
        $tracks = Track::query()->whereIn('id', $counts->pluck('track_id'))->get()->keyBy('id');
        $trackDist = $counts->map(fn ($row) => [
            'trackId' => $row->track_id,
            'code' => $tracks->get($row->track_id)?->code,
            'nameEn' => $tracks->get($row->track_id)?->name_en,
            'count' => (int) $row->getAttribute('c'),
        ])->values()->all();

        $evalTotal = Evaluation::query()->count();
        $evalSubmitted = Evaluation::query()->where('status', EvaluationStatus::Submitted)->count();

        return [
            'teams' => Team::query()->count(),
            'members' => TeamMember::query()->count(),
            'users' => User::query()->count(),
            'submissions' => Submission::query()->count(),
            'submittedProjects' => Submission::query()->whereNotNull('submitted_at')->count(),
            'trackDistribution' => $trackDist,
            'recentTeams' => Team::query()->with('track')->latest()->limit(10)->get()->map(fn ($t) => [
                'id' => $t->id,
                'teamCode' => $t->team_code,
                'name' => $t->name,
                'status' => $t->status->value,
                'createdAt' => optional($t->created_at)?->toIso8601ZuluString(),
            ])->all(),
            'recentSubmissions' => Submission::query()->latest()->limit(10)->get()->map(fn ($s) => [
                'id' => $s->id,
                'submissionCode' => $s->submission_code,
                'status' => $s->status->value,
                'submittedAt' => optional($s->submitted_at)?->toIso8601ZuluString(),
            ])->all(),
            'evaluationProgress' => [
                'total' => $evalTotal,
                'submitted' => $evalSubmitted,
            ],
        ];
    }
}
