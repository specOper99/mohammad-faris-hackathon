<?php

use App\Enums\SubmissionStatus;
use App\Models\Evaluation;
use App\Models\JudgeProfile;
use App\Models\ScoringCriterion;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\AccountActivation;
use Illuminate\Support\Facades\Notification;

function makeLeader(): User
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

it('unassigned judge GET is 404', function () {
    $leader = makeLeader();
    asUser($leader)->postJson('/api/v1/submissions');
    $sub = Submission::query()->where('team_id', $leader->currentTeam()->id)->first();
    $judgeUser = User::factory()->create();
    $judgeUser->assignRole('judge');
    JudgeProfile::factory()->create(['user_id' => $judgeUser->id]);
    asUser($judgeUser->fresh())->getJson('/api/v1/judge/submissions/'.$sub->id)
        ->assertNotFound();
});

it('assigned judge can score then cannot PUT after submit; admin reopen allows PUT', function () {
    $leader = makeLeader();
    asUser($leader)->postJson('/api/v1/submissions');
    $sub = Submission::query()->where('team_id', $leader->currentTeam()->id)->first();
    $sub->status = SubmissionStatus::Submitted;
    $sub->submitted_at = now();
    $sub->submission_code = 'SUB-2026-00099';
    $sub->save();

    $admin = User::query()->role('admin')->first();
    $judgeUser = User::factory()->create(['email' => 'judge'.uniqid().'@example.com']);
    asUser($admin)->postJson('/api/v1/admin/judges', [
        'email' => $judgeUser->email,
        'firstName' => $judgeUser->first_name,
        'lastName' => $judgeUser->last_name,
        'specialization' => 'LC',
    ])->assertCreated();
    $profile = JudgeProfile::query()->where('user_id', $judgeUser->id)->first()
        ?? JudgeProfile::query()->latest()->first();

    asUser($admin)->postJson('/api/v1/admin/assignments', [
        'judgeProfileId' => $profile->id,
        'submissionId' => $sub->id,
    ])->assertCreated();

    $judge = $profile->user->fresh();
    $scores = ScoringCriterion::query()->where('is_active', true)->get()->map(fn ($c) => [
        'criterionId' => $c->id,
        'score' => 80,
    ])->all();

    asUser($judge)->putJson('/api/v1/judge/submissions/'.$sub->id.'/evaluation', [
        'comments' => 'Good',
        'scores' => $scores,
    ])->assertOk();

    asUser($judge)->postJson('/api/v1/judge/submissions/'.$sub->id.'/evaluation/submit')->assertOk();

    asUser($judge)->putJson('/api/v1/judge/submissions/'.$sub->id.'/evaluation', [
        'comments' => 'Nope',
        'scores' => $scores,
    ])->assertStatus(409)->assertJsonPath('code', 'EVALUATION_IMMUTABLE');

    $eval = Evaluation::query()->where('submission_id', $sub->id)->first();
    asUser($admin)->postJson('/api/v1/admin/evaluations/'.$eval->id.'/reopen')->assertOk();
    asUser($judge)->putJson('/api/v1/judge/submissions/'.$sub->id.'/evaluation', [
        'comments' => 'Revised',
        'scores' => $scores,
        'version' => $eval->fresh()->version,
    ])->assertOk();
});

it('cannot assign judge to own team', function () {
    $leader = makeLeader();
    asUser($leader)->postJson('/api/v1/submissions');
    $sub = Submission::query()->where('team_id', $leader->currentTeam()->id)->first();
    $admin = User::query()->role('admin')->first();
    asUser($admin)->postJson('/api/v1/admin/judges', [
        'email' => $leader->email,
        'firstName' => $leader->first_name,
        'lastName' => $leader->last_name,
    ]);
    $profile = JudgeProfile::query()->where('user_id', $leader->id)->first();
    asUser($admin)->postJson('/api/v1/admin/assignments', [
        'judgeProfileId' => $profile->id,
        'submissionId' => $sub->id,
    ])->assertStatus(409)->assertJsonPath('code', 'ASSIGNMENT_SELF_TEAM');
});
