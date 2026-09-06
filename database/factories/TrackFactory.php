<?php

namespace Database\Factories;

use App\Enums\TrackDifficulty;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->randomElement(['A', 'B', 'C', 'D', 'E', 'F']);

        return [
            'code' => $code,
            'name_en' => 'Track '.$code,
            'name_ar' => 'مسار '.$code,
            'description_en' => 'Description',
            'description_ar' => 'وصف',
            'difficulty' => TrackDifficulty::Beginner,
            'focus_en' => 'Focus',
            'focus_ar' => 'تركيز',
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
