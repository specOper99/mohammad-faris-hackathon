<?php

use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->seed();
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173',
            'Accept' => 'application/json',
        ]);
    })
    ->in('Feature');

function asUser(User $user)
{
    Sanctum::actingAs($user, ['*']);

    return test();
}

function registerPayload(array $overrides = []): array
{
    $trackId = Track::query()->where('code', 'A')->value('id');

    return array_replace_recursive([
        'teamName' => 'Andromeda Team',
        'university' => 'UoB',
        'city' => 'Baghdad',
        'country' => 'IQ',
        'technicalLevel' => 'Beginner',
        'trackId' => $trackId,
        'githubUrl' => 'https://github.com/example/team',
        'acceptedRulesVersion' => 'rules-2026-1',
        'acceptedDataUsageVersion' => 'data-2026-1',
        'leader' => [
            'firstName' => 'Layla',
            'lastName' => 'Hassan',
            'email' => 'layla'.uniqid().'@example.com',
            'password' => 'Password1abc',
            'confirmPassword' => 'Password1abc',
            'locale' => 'en',
        ],
        'members' => [],
    ], $overrides);
}
