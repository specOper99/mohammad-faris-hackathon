<?php

namespace App\Support;

use App\Enums\TeamMemberStatus;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

final class MembershipGuard
{
    public function currentTeam(User $user): Team
    {
        $membership = TeamMember::query()
            ->with('team')
            ->where('user_id', $user->id)
            ->whereIn('status', [TeamMemberStatus::Invited, TeamMemberStatus::Active])
            ->first();

        if ($membership === null || $membership->team === null) {
            throw AppException::code('TEAM_NOT_FOUND', 404);
        }

        return $membership->team;
    }

    public function activeMembership(User $user, Team $team): TeamMember
    {
        $membership = TeamMember::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', TeamMemberStatus::Active)
            ->first();

        if ($membership === null) {
            throw AppException::code('NOT_FOUND', 404);
        }

        return $membership;
    }

    public function emailOnActiveTeam(string $email): bool
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->whereHas('memberships', function ($q): void {
                $q->whereIn('status', [TeamMemberStatus::Invited->value, TeamMemberStatus::Active->value]);
            })
            ->exists();
    }
}
