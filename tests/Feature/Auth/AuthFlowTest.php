<?php

use App\Models\ChallengeSettings;
use App\Models\User;
use App\Notifications\AccountActivation;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('issues csrf cookie', function () {
    $this->get('/sanctum/csrf-cookie')->assertNoContent();
});

it('registers activates and logs in then out', function () {
    Notification::fake();
    $payload = registerPayload();
    $this->postJson('/api/v1/auth/register', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.activationRequired', true);

    $token = null;
    Notification::assertSentTo(
        User::query()->where('email', $payload['leader']['email'])->first(),
        AccountActivation::class,
        function (AccountActivation $n) use (&$token) {
            $token = $n->rawToken;

            return true;
        }
    );

    $this->postJson('/api/v1/auth/activate', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.email', $payload['leader']['email']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $payload['leader']['email'],
        'password' => 'Password1abc',
    ])->assertOk()->assertJsonPath('data.user.email', $payload['leader']['email']);

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
});

it('rejects duplicate email with EMAIL_IN_USE', function () {
    Notification::fake();
    $payload = registerPayload();
    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'EMAIL_IN_USE');
});

it('rejects register outside window', function () {
    $settings = ChallengeSettings::current();
    $settings->registration_end = now()->subDay();
    $settings->save();

    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertStatus(409)
        ->assertJsonPath('code', 'REGISTRATION_CLOSED');
});

it('rejects login of unverified user', function () {
    Notification::fake();
    $payload = registerPayload();
    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $this->postJson('/api/v1/auth/login', [
        'email' => $payload['leader']['email'],
        'password' => 'Password1abc',
    ])->assertStatus(401)->assertJsonPath('code', 'ACCOUNT_NOT_ACTIVATED');
});

it('rejects activation token replay', function () {
    Notification::fake();
    $payload = registerPayload();
    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $token = null;
    Notification::assertSentTo(
        User::query()->where('email', $payload['leader']['email'])->first(),
        AccountActivation::class,
        function (AccountActivation $n) use (&$token) {
            $token = $n->rawToken;

            return true;
        }
    );
    $this->postJson('/api/v1/auth/activate', ['token' => $token])->assertOk();
    $this->postJson('/api/v1/auth/activate', ['token' => $token])
        ->assertStatus(409)
        ->assertJsonPath('code', 'ACTIVATION_CONSUMED');
});

it('settings disable registration', function () {
    $settings = ChallengeSettings::current();
    $settings->registration_enabled = false;
    $settings->save();
    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertStatus(409)
        ->assertJsonPath('code', 'REGISTRATION_CLOSED');
});

it('throttles login after lockout', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $email = 'lock'.uniqid().'@example.com';
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'WrongPass1x']);
    }
    $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'WrongPass1x'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'ACCOUNT_LOCKED');
});

it('rate limits login by IP', function () {
    $email = 'rl'.uniqid().'@example.com';
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => $email.$i, 'password' => 'WrongPass1x']);
    }
    $this->postJson('/api/v1/auth/login', ['email' => $email.'x', 'password' => 'WrongPass1x'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'RATE_LIMITED');
});

it('logout-all deletes session rows', function () {
    Notification::fake();
    $payload = registerPayload();
    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    $token = null;
    Notification::assertSentTo(
        User::query()->where('email', $payload['leader']['email'])->first(),
        AccountActivation::class,
        function (AccountActivation $n) use (&$token) {
            $token = $n->rawToken;

            return true;
        }
    );
    $this->postJson('/api/v1/auth/activate', ['token' => $token])->assertOk();
    $user = User::query()->where('email', $payload['leader']['email'])->first();
    DB::table('sessions')->insert([
        ['id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()],
        ['id' => 's2', 'user_id' => $user->id, 'payload' => 'y', 'last_activity' => time()],
    ]);
    asUser($user)->postJson('/api/v1/auth/logout-all')->assertNoContent();
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});
