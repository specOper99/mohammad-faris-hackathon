<?php

namespace Database\Factories;

use App\Enums\TeamStatus;
use App\Models\Team;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_code' => 'EXP-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => fake()->company().' Team',
            'leader_user_id' => User::factory()->participant(),
            'university' => 'University',
            'organization' => null,
            'city' => 'Baghdad',
            'country' => 'IQ',
            'technical_level' => 'Beginner',
            'track_id' => Track::query()->value('id') ?? Track::factory(),
            'status' => TeamStatus::Confirmed,
            'max_members_snapshot' => 6,
            'version' => 1,
            'is_deleted' => false,
        ];
    }
}
