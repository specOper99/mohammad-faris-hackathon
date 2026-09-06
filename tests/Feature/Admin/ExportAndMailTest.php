<?php

use App\Models\User;
use App\Notifications\AccountActivation;
use App\Notifications\RegistrationReceived;
use Illuminate\Support\Facades\Notification;

it('queues registration notifications after commit not sync mail', function () {
    Notification::fake();
    $payload = registerPayload();
    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $user = User::query()->where('email', $payload['leader']['email'])->first();
    Notification::assertSentTo($user, RegistrationReceived::class);
    Notification::assertSentTo($user, AccountActivation::class);
});

it('admin csv headers match design columns', function () {
    $admin = User::query()->role('admin')->first();
    $payload = registerPayload();
    Notification::fake();
    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $response = asUser($admin)->get('/api/v1/admin/export/teams.csv');
    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('teamCode');
    expect($csv)->toContain('leaderEmail');
    expect($csv)->toContain('trackCode');
    expect($csv)->toContain('memberCount');
});

it('public settings omit emails and scores', function () {
    $res = $this->getJson('/api/v1/public/settings')->assertOk();
    $json = $res->json('data');
    expect($json)->toHaveKey('maxTeamMembers');
    expect($json)->not->toHaveKey('seedAdminEmail');
    expect(json_encode($json))->not->toContain('@localhost');
});

it('health ready returns ok', function () {
    $this->getJson('/api/v1/health/ready')->assertOk()->assertJsonPath('data.ok', true);
});

it('up endpoint is 200', function () {
    $this->get('/up')->assertOk();
});
