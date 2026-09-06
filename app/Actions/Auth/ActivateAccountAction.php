<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Enums\OneTimeTokenPurpose;
use App\Enums\TeamStatus;
use App\Models\ChallengeSettings;
use App\Models\TeamInvitation;
use App\Notifications\AccountConfirmed;
use App\Notifications\MemberInvitation;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;
use Illuminate\Support\Facades\DB;

final class ActivateAccountAction
{
    public function __construct(
        private OneTimeTokenService $tokens,
        private OutboxNotifier $outbox,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{email: string, teamCode: string}
     */
    public function execute(string $rawToken): array
    {
        $token = $this->tokens->findValid($rawToken, OneTimeTokenPurpose::EmailActivation);
        $user = $token->user;
        if ($user === null) {
            throw AppException::code('ACTIVATION_INVALID', 409);
        }

        return DB::transaction(function () use ($token, $user) {
            $this->tokens->consume($token);
            $user->email_verified_at = now()->toImmutable();
            $user->save();

            $settings = ChallengeSettings::current();
            $membership = $user->memberships()->with('team')->where('role', 'leader')->first();
            $team = $membership?->team;
            if ($team === null) {
                throw AppException::code('TEAM_NOT_FOUND', 404);
            }

            $team->status = $settings->require_admin_team_confirmation
                ? TeamStatus::Registered
                : TeamStatus::Confirmed;
            $team->save();

            $this->outbox->send($user, new AccountConfirmed($team->team_code), ['team' => $team->team_code]);

            $invites = TeamInvitation::query()
                ->with(['token', 'member.user'])
                ->where('team_id', $team->id)
                ->where('status', InvitationStatus::Pending)
                ->get();

            foreach ($invites as $invite) {
                $invitee = $invite->member->user;
                if ($invitee === null) {
                    continue;
                }
                $previous = $invite->token;
                if ($previous !== null) {
                    $this->tokens->consume($previous);
                }
                $fresh = $this->tokens->issue(
                    OneTimeTokenPurpose::TeamInvitation,
                    $invitee,
                    now()->toImmutable()->addDays((int) config('exoplanet.invitation_ttl_days', 7)),
                    ['team_id' => $team->id, 'invitation_id' => $invite->id],
                );
                $invite->one_time_token_id = $fresh['model']->id;
                $invite->save();
                $this->outbox->send(
                    $invitee,
                    new MemberInvitation($fresh['token'], $team->team_code),
                    ['invitation_id' => $invite->id],
                );
            }

            $this->audit->write(AuditAction::AUTH_ACTIVATE, $user, null, ['team' => $team->team_code], $user);

            return ['email' => $user->email, 'teamCode' => $team->team_code];
        });
    }
}
