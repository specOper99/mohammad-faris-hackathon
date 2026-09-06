<?php

namespace App\Actions\Members;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Enums\OneTimeTokenPurpose;
use App\Enums\TeamMemberRole;
use App\Enums\TeamMemberStatus;
use App\Enums\TeamStatus;
use App\Models\ChallengeSettings;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\MemberInvitation;
use App\Policies\TeamPolicy;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\Clock;
use App\Support\MembershipGuard;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InviteMemberAction
{
    public function __construct(
        private MembershipGuard $guard,
        private OneTimeTokenService $tokens,
        private OutboxNotifier $outbox,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): TeamMember
    {
        $team = $this->guard->currentTeam($actor);
        if (! app(TeamPolicy::class)->update($actor, $team)) {
            throw AppException::code('NOT_TEAM_LEADER', 403);
        }
        if (in_array($team->status, [TeamStatus::Rejected, TeamStatus::Cancelled], true)) {
            throw AppException::code('TEAM_STATUS_INVALID', 409);
        }

        $settings = ChallengeSettings::current();
        $count = TeamMember::query()
            ->where('team_id', $team->id)
            ->whereIn('status', [TeamMemberStatus::Invited, TeamMemberStatus::Active])
            ->count();
        if ($count >= $settings->max_team_members) {
            throw AppException::code('TEAM_MEMBER_LIMIT', 409);
        }

        $email = strtolower($data['email']);
        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($existing && ($existing->hasRole('judge') || $existing->hasRole('admin'))) {
            throw AppException::code('ACCOUNT_ROLE_CONFLICT', 409);
        }
        if ($existing && TeamMember::query()->where('user_id', $existing->id)->whereIn('status', [TeamMemberStatus::Invited, TeamMemberStatus::Active])->exists()) {
            throw AppException::code('USER_ALREADY_ON_TEAM', 409);
        }

        return DB::transaction(function () use ($actor, $team, $data, $email, $existing) {
            $user = $existing ?? User::query()->create([
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'email' => $email,
                'password' => Str::password(40),
                'is_active' => true,
            ]);
            if ($existing === null) {
                $user->assignRole('participant');
            }

            $member = TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => TeamMemberRole::Member,
                'skill' => $data['skill'] ?? null,
                'status' => TeamMemberStatus::Invited,
                'invited_at' => $this->clock->now(),
            ]);

            $issued = $this->tokens->issue(
                OneTimeTokenPurpose::TeamInvitation,
                $user,
                $this->clock->now()->addDays((int) config('exoplanet.invitation_ttl_days', 7)),
                ['team_id' => $team->id],
            );

            TeamInvitation::query()->create([
                'team_id' => $team->id,
                'team_member_id' => $member->id,
                'email' => $email,
                'invited_by_user_id' => $actor->id,
                'one_time_token_id' => $issued['model']->id,
                'status' => InvitationStatus::Pending,
            ]);

            $this->outbox->send($user, new MemberInvitation($issued['token'], $team->team_code));
            $this->audit->write(AuditAction::MEMBER_INVITE, $member, null, ['email' => $email], $actor);

            return $member;
        });
    }
}
