<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'track_id' => fn (array $attrs) => Team::query()->find($attrs['team_id'])?->track_id,
            'project_name' => 'Project',
            'abstract' => 'Abstract text',
            'problem_description' => 'Problem',
            'solution_description' => 'Solution',
            'github_url' => 'https://github.com/example/repo',
            'status' => SubmissionStatus::Draft,
            'publication_status' => PublicationStatus::Private,
            'version' => 1,
            'created_by_user_id' => User::factory(),
            'reopen_count' => 0,
        ];
    }
}
