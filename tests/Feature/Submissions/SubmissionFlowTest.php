<?php

use App\Models\ChallengeSettings;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\AccountActivation;
use App\Services\Storage\ObjectStorage;
use Illuminate\Support\Facades\Notification;

function leaderReady(): User
{
    Notification::fake();
    $payload = registerPayload();
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

function draftFor(User $user): Submission
{
    asUser($user)->postJson('/api/v1/submissions')->assertOk();

    return Submission::query()->where('team_id', $user->currentTeam()->id)->firstOrFail();
}

function fillNarrative(User $user, Submission $sub): void
{
    asUser($user)->putJson('/api/v1/submissions/'.$sub->id, [
        'projectName' => 'Transit Detect',
        'abstract' => 'An abstract of the project that is long enough.',
        'problemDescription' => 'Problem text',
        'solutionDescription' => 'Solution text',
        'githubUrl' => 'https://github.com/example/repo',
        'limitations' => 'Limitations text',
        'aiUsage' => 'No generative AI.',
        'version' => $sub->fresh()->version,
    ])->assertOk();
}

function attachRequiredFiles(User $user, Submission $sub): void
{
    $storage = app(ObjectStorage::class);
    foreach ([
        ['report_pdf', 'report.pdf', 'application/pdf', '%PDF-1.4 test'],
        ['readme', 'README.md', 'text/markdown', '# hi'],
    ] as [$type, $name, $mime, $bytes]) {
        $init = asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/files/uploads', [
            'fileType' => $type,
            'fileName' => $name,
            'mimeType' => $mime,
            'sizeBytes' => strlen($bytes),
        ])->assertOk();
        $key = $init->json('data.storageKey');
        $storage->put($key, $bytes, $mime);
        asUser($user)->postJson(
            '/api/v1/submissions/'.$sub->id.'/files/uploads/'.$init->json('data.uploadSessionId').'/complete'
        )->assertCreated();
    }
}

it('rejects submit missing PDF with SUBMISSION_REQUIRED_FILES', function () {
    $user = leaderReady();
    $sub = draftFor($user);
    fillNarrative($user, $sub);
    asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/submit')
        ->assertStatus(422)
        ->assertJsonPath('code', 'SUBMISSION_REQUIRED_FILES');
});

it('rejects submit after deadline unchanged', function () {
    $user = leaderReady();
    $sub = draftFor($user);
    fillNarrative($user, $sub);
    attachRequiredFiles($user, $sub->fresh());
    $settings = ChallengeSettings::current();
    $settings->submission_end = now()->subSecond();
    $settings->save();
    $before = $sub->fresh()->toArray();
    asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/submit')
        ->assertStatus(409)
        ->assertJsonPath('code', 'SUBMISSION_DEADLINE_PASSED');
    expect($sub->fresh()->submission_code)->toBeNull();
    expect($sub->fresh()->status->value)->toBe('draft');
});

it('second submit returns 409 same code', function () {
    $user = leaderReady();
    $sub = draftFor($user);
    fillNarrative($user, $sub);
    attachRequiredFiles($user, $sub->fresh());
    $first = asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/submit')->assertOk();
    $code = $first->json('data.submissionCode');
    asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/submit')
        ->assertStatus(409)
        ->assertJsonPath('code', 'SUBMISSION_ALREADY_SUBMITTED');
    expect($sub->fresh()->submission_code)->toBe($code);
});

it('overlapping sequential submit one 200 one 409', function () {
    $user = leaderReady();
    $sub = draftFor($user);
    fillNarrative($user, $sub);
    attachRequiredFiles($user, $sub->fresh());
    asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/submit')->assertOk();
    asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/submit')->assertStatus(409);
});

it('IDOR user B cannot GET A submission', function () {
    $a = leaderReady();
    $sub = draftFor($a);
    $b = leaderReady();
    asUser($b)->getJson('/api/v1/submissions/'.$sub->id)
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

it('initiate put complete download with fake storage', function () {
    $user = leaderReady();
    $sub = draftFor($user);
    $bytes = '%PDF-1.4 hello';
    $init = asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/files/uploads', [
        'fileType' => 'report_pdf',
        'fileName' => 'report.pdf',
        'mimeType' => 'application/pdf',
        'sizeBytes' => strlen($bytes),
    ])->assertOk();
    app(ObjectStorage::class)->put($init->json('data.storageKey'), $bytes, 'application/pdf');
    $complete = asUser($user)->postJson(
        '/api/v1/submissions/'.$sub->id.'/files/uploads/'.$init->json('data.uploadSessionId').'/complete'
    )->assertCreated();
    asUser($user)->getJson('/api/v1/submissions/'.$sub->id.'/files/'.$complete->json('data.id').'/download')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('magic byte mismatch 422', function () {
    $user = leaderReady();
    $sub = draftFor($user);
    $bytes = 'NOTAPDF!!';
    $init = asUser($user)->postJson('/api/v1/submissions/'.$sub->id.'/files/uploads', [
        'fileType' => 'report_pdf',
        'fileName' => 'report.pdf',
        'mimeType' => 'application/pdf',
        'sizeBytes' => strlen($bytes),
    ])->assertOk();
    app(ObjectStorage::class)->put($init->json('data.storageKey'), $bytes, 'application/pdf');
    asUser($user)->postJson(
        '/api/v1/submissions/'.$sub->id.'/files/uploads/'.$init->json('data.uploadSessionId').'/complete'
    )->assertStatus(422)->assertJsonPath('code', 'FILE_CONTENT_MISMATCH');
});
