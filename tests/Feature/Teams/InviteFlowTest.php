<?php

use App\Models\ChallengeSettings;
use App\Models\User;
use App\Notifications\AccountActivation;
use App\Notifications\MemberInvitation;
use Illuminate\Support\Facades\Notification;

function activateLeader(array $payload): User
{
    Notification::fake();
    test()->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $user = User::query()->where('email', $payload['leader']['email'])->first();
    $token = null;
    Notification::assertSentTo($user, AccountActivation::class, function (AccountActivation $n) use (&$token) {
        $token = $n->rawToken;

        return true;
    });
    test()->postJson('/api/v1/auth/activate', ['token' => $token])->assertOk();

    return $user->fresh();
}

it('invite accept lets member view but not update team', function () {
    $payload = registerPayload();
    $leader = activateLeader($payload);
    asUser($leader)->postJson('/api/v1/teams/me/members', [
        'firstName' => 'Omar',
        'lastName' => 'Ali',
        'email' => 'omar'.uniqid().'@example.com',
        'skill' => 'ML',
    ])->assertCreated();

    $inviteToken = null;
    Notification::assertSentTo(
        User::query()->where('email', 'like', 'omar%')->first(),
        MemberInvitation::class,
        function (MemberInvitation $n) use (&$inviteToken) {
            $inviteToken = $n->rawToken;

            return true;
        }
    );

    $this->postJson('/api/v1/auth/invitations/accept', [
        'token' => $inviteToken,
        'password' => 'Password1abc',
        'confirmPassword' => 'Password1abc',
    ])->assertOk();

    $member = User::query()->where('email', 'like', 'omar%')->first();
    asUser($member)->getJson('/api/v1/teams/me')->assertOk();
    asUser($member)->putJson('/api/v1/teams/me', ['teamName' => 'Hacked', 'version' => 1])
        ->assertStatus(403);
});

it('rejects invite of email already on another team', function () {
    $a = activateLeader(registerPayload());
    $email = 'shared'.uniqid().'@example.com';
    asUser($a)->postJson('/api/v1/teams/me/members', [
        'firstName' => 'A', 'lastName' => 'B', 'email' => $email,
    ])->assertCreated();

    $b = activateLeader(registerPayload());
    asUser($b)->postJson('/api/v1/teams/me/members', [
        'firstName' => 'A', 'lastName' => 'B', 'email' => $email,
    ])->assertStatus(409)->assertJsonPath('code', 'USER_ALREADY_ON_TEAM');
});

it('rejects max members plus one', function () {
    $settings = ChallengeSettings::current();
    $settings->max_team_members = 2;
    $settings->save();
    $leader = activateLeader(registerPayload());
    asUser($leader)->postJson('/api/v1/teams/me/members', [
        'firstName' => 'A', 'lastName' => 'B', 'email' => 'm1'.uniqid().'@example.com',
    ])->assertCreated();
    asUser($leader)->postJson('/api/v1/teams/me/members', [
        'firstName' => 'C', 'lastName' => 'D', 'email' => 'm2'.uniqid().'@example.com',
    ])->assertStatus(409)->assertJsonPath('code', 'TEAM_MEMBER_LIMIT');
});

it('hides other team with 404', function () {
    $a = activateLeader(registerPayload());
    $b = activateLeader(registerPayload());
    $teamA = $a->currentTeam();
    asUser($b)->getJson('/api/v1/teams/me')->assertOk()->assertJsonPath('data.id', $b->currentTeam()->id);
    expect($teamA->id)->not->toBe($b->currentTeam()->id);
});
