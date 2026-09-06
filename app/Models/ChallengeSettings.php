<?php

namespace App\Models;

use App\Enums\ScoreAggregation;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasOptimisticLock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'challenge_year',
    'registration_enabled',
    'registration_start',
    'registration_end',
    'submission_enabled',
    'submission_start',
    'submission_end',
    'scoring_enabled',
    'publish_results',
    'max_team_members',
    'allow_multiple_teams',
    'require_admin_team_confirmation',
    'one_active_track_per_team',
    'lock_on_submit',
    'lock_on_deadline',
    'allow_participant_edits_after_submit',
    'all_members_can_edit',
    'email_all_members_on_submit',
    'score_aggregation',
    'current_rules_version',
    'current_data_usage_version',
    'allowed_track_ids',
    'file_policy',
    'required_file_types_on_submit',
    'required_fields_on_submit',
    'required_file_types_by_track_code',
    'allow_direct_upload_below_bytes',
    'version',
    'updated_by_user_id',
])]
/**
 * @property ScoreAggregation $score_aggregation
 * @property array<int, string>|null $allowed_track_ids
 * @property array<string, mixed> $file_policy
 * @property array<int, string> $required_file_types_on_submit
 * @property array<int, string> $required_fields_on_submit
 * @property array<string, mixed> $required_file_types_by_track_code
 * @property CarbonImmutable $registration_start
 * @property CarbonImmutable $registration_end
 * @property CarbonImmutable $submission_start
 * @property CarbonImmutable $submission_end
 */
class ChallengeSettings extends Model
{
    use Auditable, HasOptimisticLock, HasUuids;

    public $incrementing = false;

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challenge_year' => 'integer',
            'registration_enabled' => 'boolean',
            'registration_start' => 'immutable_datetime',
            'registration_end' => 'immutable_datetime',
            'submission_enabled' => 'boolean',
            'submission_start' => 'immutable_datetime',
            'submission_end' => 'immutable_datetime',
            'scoring_enabled' => 'boolean',
            'publish_results' => 'boolean',
            'max_team_members' => 'integer',
            'allow_multiple_teams' => 'boolean',
            'require_admin_team_confirmation' => 'boolean',
            'one_active_track_per_team' => 'boolean',
            'lock_on_submit' => 'boolean',
            'lock_on_deadline' => 'boolean',
            'allow_participant_edits_after_submit' => 'boolean',
            'all_members_can_edit' => 'boolean',
            'email_all_members_on_submit' => 'boolean',
            'score_aggregation' => ScoreAggregation::class,
            'allowed_track_ids' => 'array',
            'file_policy' => 'array',
            'required_file_types_on_submit' => 'array',
            'required_fields_on_submit' => 'array',
            'required_file_types_by_track_code' => 'array',
            'allow_direct_upload_below_bytes' => 'integer',
            'version' => 'integer',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->find(config('exoplanet.settings_id'));
        if (! $row instanceof self) {
            throw new \RuntimeException('Challenge settings missing.');
        }

        return $row;
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
