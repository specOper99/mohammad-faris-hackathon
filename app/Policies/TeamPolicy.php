<?php

namespace App\Policies;

use App\Enums\TeamMemberStatus;
use App\Models\ChallengeSettings;
use App\Models\Team;
use App\Models\User;

final class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $team->members()
            ->where('user_id', $user->id)
            ->where('status', TeamMemberStatus::Active)
            ->exists();
    }

    public function update(User $user, Team $team): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $member = $team->members()
            ->where('user_id', $user->id)
            ->where('status', TeamMemberStatus::Active)
            ->first();

        if ($member === null) {
            return false;
        }

        if ($member->role->value === 'leader') {
            return true;
        }

        return ChallengeSettings::current()->all_members_can_edit;
    }
}
