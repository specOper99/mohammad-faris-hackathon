<?php

namespace App\Actions\Teams;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AppException;
use App\Support\MembershipGuard;

final class ChangeTrackAction
{
    public function __construct(private MembershipGuard $guard) {}

    /**
     * @param  array{trackId: string}  $data
     */
    public function execute(User $user, array $data): void
    {
        $team = $this->guard->currentTeam($user);
        if (! app(TeamPolicy::class)->update($user, $team)) {
            throw AppException::code('FORBIDDEN', 403);
        }

        $submission = Submission::query()->where('team_id', $team->id)->first();
        if ($submission && $submission->status !== SubmissionStatus::Draft) {
            throw AppException::code('TRACK_FROZEN', 409);
        }

        $track = Track::query()->find($data['trackId']);
        if ($track === null || ! $track->is_active) {
            throw AppException::code('TRACK_INACTIVE', 409);
        }

        $team->track_id = $track->id;
        $team->save();
        if ($submission) {
            $submission->track_id = $track->id;
            $submission->save();
        }
    }
}
