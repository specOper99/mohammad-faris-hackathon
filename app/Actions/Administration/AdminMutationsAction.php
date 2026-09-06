<?php

namespace App\Actions\Administration;

use App\Domain\Scoring\WeightSumValidator;
use App\Enums\TeamMemberRole;
use App\Enums\TeamMemberStatus;
use App\Enums\TeamStatus;
use App\Models\ChallengeSettings;
use App\Models\ScoringCriterion;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\AppException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AdminMutationsAction
{
    public function updateTeamStatus(Team $team, TeamStatus $status, ?string $reason): Team
    {
        $team->status = $status;
        $team->rejected_reason = $reason;
        $team->save();

        return $team;
    }

    public function transferLeadership(Team $team, string $newLeaderUserId): Team
    {
        $new = TeamMember::query()
            ->where('team_id', $team->id)
            ->where('user_id', $newLeaderUserId)
            ->where('status', TeamMemberStatus::Active)
            ->first();
        if ($new === null) {
            throw AppException::code('MEMBER_NOT_FOUND', 404);
        }

        $old = TeamMember::query()
            ->where('team_id', $team->id)
            ->where('role', TeamMemberRole::Leader)
            ->where('status', TeamMemberStatus::Active)
            ->first();

        DB::transaction(function () use ($team, $new, $old, $newLeaderUserId): void {
            if ($old) {
                $old->role = TeamMemberRole::Member;
                $old->save();
            }
            $new->role = TeamMemberRole::Leader;
            $new->save();
            $team->leader_user_id = $newLeaderUserId;
            $team->save();
        });

        return $team->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(ChallengeSettings $settings, array $data, User $admin): ChallengeSettings
    {
        $start = isset($data['registrationStart']) ? CarbonImmutable::parse($data['registrationStart']) : $settings->registration_start;
        $end = isset($data['registrationEnd']) ? CarbonImmutable::parse($data['registrationEnd']) : $settings->registration_end;
        $sStart = isset($data['submissionStart']) ? CarbonImmutable::parse($data['submissionStart']) : $settings->submission_start;
        $sEnd = isset($data['submissionEnd']) ? CarbonImmutable::parse($data['submissionEnd']) : $settings->submission_end;
        if ($start->gt($end) || $sStart->gt($sEnd)) {
            throw AppException::code('SETTINGS_INVALID_WINDOW', 422);
        }
        $max = (int) ($data['maxTeamMembers'] ?? $settings->max_team_members);
        if ($max < 2 || $max > 20) {
            throw AppException::code('VALIDATION_FAILED', 422, ['maxTeamMembers' => ['Must be 2-20.']]);
        }

        $attrs = [
            'registration_enabled' => $data['registrationEnabled'] ?? $settings->registration_enabled,
            'registration_start' => $start,
            'registration_end' => $end,
            'submission_enabled' => $data['submissionEnabled'] ?? $settings->submission_enabled,
            'submission_start' => $sStart,
            'submission_end' => $sEnd,
            'scoring_enabled' => $data['scoringEnabled'] ?? $settings->scoring_enabled,
            'publish_results' => $data['publishResults'] ?? $settings->publish_results,
            'max_team_members' => $max,
            'allow_multiple_teams' => $data['allowMultipleTeams'] ?? $settings->allow_multiple_teams,
            'require_admin_team_confirmation' => $data['requireAdminTeamConfirmation'] ?? $settings->require_admin_team_confirmation,
            'lock_on_submit' => $data['lockOnSubmit'] ?? $settings->lock_on_submit,
            'lock_on_deadline' => $data['lockOnDeadline'] ?? $settings->lock_on_deadline,
            'all_members_can_edit' => $data['allMembersCanEdit'] ?? $settings->all_members_can_edit,
            'email_all_members_on_submit' => $data['emailAllMembersOnSubmit'] ?? $settings->email_all_members_on_submit,
            'score_aggregation' => $data['scoreAggregation'] ?? $settings->score_aggregation,
            'current_rules_version' => $data['currentRulesVersion'] ?? $settings->current_rules_version,
            'current_data_usage_version' => $data['currentDataUsageVersion'] ?? $settings->current_data_usage_version,
            'allowed_track_ids' => $data['allowedTrackIds'] ?? $settings->allowed_track_ids,
            'file_policy' => $data['filePolicy'] ?? $settings->file_policy,
            'required_file_types_on_submit' => $data['requiredFileTypesOnSubmit'] ?? $settings->required_file_types_on_submit,
            'required_fields_on_submit' => $data['requiredFieldsOnSubmit'] ?? $settings->required_fields_on_submit,
            'required_file_types_by_track_code' => $data['requiredFileTypesByTrackCode'] ?? $settings->required_file_types_by_track_code,
            'allow_direct_upload_below_bytes' => $data['allowDirectUploadBelowBytes'] ?? $settings->allow_direct_upload_below_bytes,
            'updated_by_user_id' => $admin->id,
        ];
        $settings->saveWithVersion($attrs, isset($data['version']) ? (int) $data['version'] : $settings->version);

        return $settings->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function replaceCriteria(array $items, WeightSumValidator $validator): void
    {
        $validator->assert(array_map(fn ($i) => (float) $i['weight'], $items));
        DB::transaction(function () use ($items): void {
            $keep = [];
            foreach ($items as $i => $item) {
                $row = ScoringCriterion::query()->updateOrCreate(
                    ['code' => $item['code']],
                    [
                        'name_en' => $item['nameEn'],
                        'name_ar' => $item['nameAr'],
                        'weight' => $item['weight'],
                        'min_score' => $item['minScore'] ?? 0,
                        'max_score' => $item['maxScore'] ?? 100,
                        'sort_order' => $item['sortOrder'] ?? $i,
                        'is_active' => $item['isActive'] ?? true,
                    ],
                );
                $keep[] = $row->id;
            }
            ScoringCriterion::query()->whereNotIn('id', $keep)->update(['is_active' => false]);
        });
    }
}
