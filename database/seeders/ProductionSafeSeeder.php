<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSafeSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TrackSeeder::class,
            ScoringCriterionSeeder::class,
            ChallengeSettingsSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
