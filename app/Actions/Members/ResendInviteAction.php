<?php

namespace App\Actions\Members;

use App\Enums\InvitationStatus;
use App\Enums\OneTimeTokenPurpose;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\MemberInvitation;
use App\Policies\TeamPolicy;
use App\Support\AppException;
use App\Support\Clock;
use App\Support\MembershipGuard;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;

final class ResendInviteAction
{
    public function __construct(
        private MembershipGuard $guard,
        private OneTimeTokenService $tokens,
        private OutboxNotifier $outbox,
        private Clock $clock,
    ) {}

    public function execute(User $actor, string $memberId): void
    {
        $team = $this->guard->currentTeam($actor);
        if (! app(TeamPolicy::class)->update($actor, $team)) {
            throw AppException::code('NOT_TEAM_LEADER', 403);
        }

        $member = TeamMember::query()->where('team_id', $team->id)->where('id', $memberId)->first();
        $invite = TeamInvitation::query()->where('team_member_id', $memberId)->where('status', InvitationStatus::Pending)->first();
        if ($member === null || $invite === null) {
            throw AppException::code('INVITATION_INVALID', 409);
        }

        $invitee = $member->user;
        if ($invitee === null) {
            throw AppException::code('INVITATION_INVALID', 409);
        }

        $this->tokens->invalidateUnused($invitee, OneTimeTokenPurpose::TeamInvitation);
        $issued = $this->tokens->issue(
            OneTimeTokenPurpose::TeamInvitation,
            $invitee,
            $this->clock->now()->addDays((int) config('exoplanet.invitation_ttl_days', 7)),
            ['team_id' => $team->id],
        );
        $invite->one_time_token_id = $issued['model']->id;
        $invite->save();
        $this->outbox->send($invitee, new MemberInvitation($issued['token'], $team->team_code));
    }
}
