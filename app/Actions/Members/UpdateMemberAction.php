<?php

namespace App\Actions\Members;

use App\Models\TeamMember;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AppException;
use App\Support\MembershipGuard;

final class UpdateMemberAction
{
    public function __construct(private MembershipGuard $guard) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, string $memberId, array $data): TeamMember
    {
        $team = $this->guard->currentTeam($actor);
        if (! app(TeamPolicy::class)->update($actor, $team)) {
            throw AppException::code('NOT_TEAM_LEADER', 403);
        }

        $member = TeamMember::query()->where('team_id', $team->id)->where('id', $memberId)->first();
        if ($member === null) {
            throw AppException::code('MEMBER_NOT_FOUND', 404);
        }
        $member->skill = $data['skill'] ?? $member->skill;
        $member->save();

        return $member;
    }
}
