<?php

namespace App\Actions\Submissions;

use App\Enums\PublicationStatus;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use App\Support\MembershipGuard;
use Illuminate\Database\UniqueConstraintViolationException;

final class GetOrCreateDraftAction
{
    public function __construct(private MembershipGuard $guard) {}

    public function execute(User $user): Submission
    {
        $team = $this->guard->currentTeam($user);
        $existing = Submission::query()->where('team_id', $team->id)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return Submission::query()->create([
                'team_id' => $team->id,
                'track_id' => $team->track_id,
                'status' => SubmissionStatus::Draft,
                'publication_status' => PublicationStatus::Private,
                'version' => 1,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Submission::query()->where('team_id', $team->id)->firstOrFail();
        }
    }
}
