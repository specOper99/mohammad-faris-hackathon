<?php

namespace Database\Seeders;

use App\Enums\ScoreAggregation;
use App\Models\ChallengeSettings;
use App\Models\Track;
use Illuminate\Database\Seeder;

class ChallengeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (ChallengeSettings::query()->exists()) {
            return;
        }

        $ids = Track::query()->where('is_active', true)->pluck('id')->all();

        ChallengeSettings::query()->create([
            'id' => config('exoplanet.settings_id'),
            'challenge_year' => (int) config('exoplanet.challenge_year', 2026),
            'registration_enabled' => true,
            'registration_start' => now()->subMonth(),
            'registration_end' => now()->addYear(),
            'submission_enabled' => true,
            'submission_start' => now()->subMonth(),
            'submission_end' => now()->addYear(),
            'scoring_enabled' => true,
            'publish_results' => false,
            'max_team_members' => 6,
            'allow_multiple_teams' => false,
            'require_admin_team_confirmation' => false,
            'one_active_track_per_team' => true,
            'lock_on_submit' => true,
            'lock_on_deadline' => true,
            'allow_participant_edits_after_submit' => false,
            'all_members_can_edit' => false,
            'email_all_members_on_submit' => true,
            'score_aggregation' => ScoreAggregation::Average,
            'current_rules_version' => 'rules-2026-1',
            'current_data_usage_version' => 'data-2026-1',
            'allowed_track_ids' => $ids,
            'file_policy' => config('exoplanet.file_policy'),
            'required_file_types_on_submit' => ['report_pdf', 'readme'],
            'required_fields_on_submit' => [
                'projectName',
                'abstract',
                'problemDescription',
                'solutionDescription',
                'githubUrl',
                'limitations',
                'aiUsage',
            ],
            'required_file_types_by_track_code' => [],
            'allow_direct_upload_below_bytes' => 0,
            'version' => 1,
        ]);
    }
}
