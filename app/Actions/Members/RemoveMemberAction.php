<?php

namespace App\Actions\Members;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Enums\TeamMemberRole;
use App\Enums\TeamMemberStatus;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\MembershipGuard;

final class RemoveMemberAction
{
    public function __construct(
        private MembershipGuard $guard,
        private AuditLogger $audit,
    ) {}

    public function execute(User $actor, string $memberId): void
    {
        $team = $this->guard->currentTeam($actor);
        if (! app(TeamPolicy::class)->update($actor, $team)) {
            throw AppException::code('NOT_TEAM_LEADER', 403);
        }

        $member = TeamMember::query()->where('team_id', $team->id)->where('id', $memberId)->first();
        if ($member === null) {
            throw AppException::code('MEMBER_NOT_FOUND', 404);
        }
        if ($member->role === TeamMemberRole::Leader) {
            throw AppException::code('CANNOT_REMOVE_LEADER', 409);
        }

        $member->status = TeamMemberStatus::Removed;
        $member->removed_at = now()->toImmutable();
        $member->save();

        TeamInvitation::query()
            ->where('team_member_id', $member->id)
            ->where('status', InvitationStatus::Pending)
            ->update(['status' => InvitationStatus::Cancelled, 'cancelled_at' => now()]);

        $user = $member->user;
        if ($user !== null && $user->email_verified_at === null) {
            $user->is_active = false;
            $user->save();
        }

        $this->audit->write(AuditAction::MEMBER_REMOVE, $member, null, null, $actor);
    }
}
