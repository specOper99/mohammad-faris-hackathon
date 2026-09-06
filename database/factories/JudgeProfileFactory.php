<?php

namespace Database\Factories;

use App\Models\JudgeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JudgeProfile>
 */
class JudgeProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->judge(),
            'specialization' => 'Exoplanets',
            'is_active' => true,
        ];
    }
}
