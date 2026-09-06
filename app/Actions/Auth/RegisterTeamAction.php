<?php

namespace App\Actions\Auth;

use App\Domain\Codes\PublicCodeFormatter;
use App\Domain\Registration\RegistrationWindow;
use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Enums\OneTimeTokenPurpose;
use App\Enums\TeamMemberRole;
use App\Enums\TeamMemberStatus;
use App\Enums\TeamStatus;
use App\Models\ChallengeSettings;
use App\Models\Team;
use App\Models\TeamAgreement;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\Track;
use App\Models\User;
use App\Notifications\AccountActivation;
use App\Notifications\RegistrationReceived;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\Clock;
use App\Support\CodeSequence;
use App\Support\HttpsUrl;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterTeamAction
{
    public function __construct(
        private Clock $clock,
        private RegistrationWindow $window,
        private CodeSequence $sequences,
        private PublicCodeFormatter $codes,
        private OneTimeTokenService $tokens,
        private OutboxNotifier $outbox,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{teamId: string, teamCode: string, leaderUserId: string, activationRequired: bool}
     */
    public function execute(array $data): array
    {
        $settings = ChallengeSettings::current();
        $now = $this->clock->now();

        if ($this->window->notStarted($now, $settings->registration_start)) {
            throw AppException::code('REGISTRATION_NOT_STARTED', 409);
        }
        if (! $this->window->canRegister($now, $settings->registration_enabled, $settings->registration_start, $settings->registration_end)) {
            throw AppException::code('REGISTRATION_CLOSED', 409);
        }

        $track = Track::query()->find($data['trackId']);
        if ($track === null || ! $track->is_active) {
            throw AppException::code('TRACK_INACTIVE', 409);
        }
        $allowed = $settings->allowed_track_ids ?? [];
        if ($allowed !== [] && ! in_array($track->id, $allowed, true)) {
            throw AppException::code('TRACK_NOT_ALLOWED', 409);
        }

        if ($data['acceptedRulesVersion'] !== $settings->current_rules_version
            || $data['acceptedDataUsageVersion'] !== $settings->current_data_usage_version) {
            throw AppException::code('VALIDATION_FAILED', 422, [
                'acceptedRulesVersion' => ['Agreement versions must match current settings.'],
            ]);
        }

        $members = $data['members'] ?? [];
        if (1 + count($members) > $settings->max_team_members) {
            throw AppException::code('TEAM_MEMBER_LIMIT', 409);
        }

        foreach ([$data['githubUrl'] ?? null, $data['portfolioUrl'] ?? null] as $url) {
            if ($url && ! HttpsUrl::isValid($url)) {
                throw AppException::code('VALIDATION_FAILED', 422, ['githubUrl' => ['HTTPS URL required.']]);
            }
        }

        $leaderEmail = strtolower($data['leader']['email']);
        $this->assertEmailAvailable($leaderEmail);
        foreach ($members as $member) {
            $this->assertEmailAvailable(strtolower($member['email']));
        }

        return DB::transaction(function () use ($data, $settings, $track, $members, $now, $leaderEmail) {
            $leader = User::query()->create([
                'first_name' => $data['leader']['firstName'],
                'last_name' => $data['leader']['lastName'],
                'email' => $leaderEmail,
                'phone' => $data['leader']['phone'] ?? null,
                'password' => $data['leader']['password'],
                'locale' => $data['leader']['locale'] ?? 'en',
                'is_active' => true,
                'email_verified_at' => null,
            ]);
            $leader->assignRole('participant');

            $year = (int) $settings->challenge_year;
            $code = $this->codes->team($year, $this->sequences->next('team_code_seq'));

            $team = Team::query()->create([
                'team_code' => $code,
                'name' => $data['teamName'],
                'leader_user_id' => $leader->id,
                'university' => $data['university'] ?? null,
                'organization' => $data['organization'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'technical_level' => $data['technicalLevel'],
                'track_id' => $track->id,
                'github_url' => $data['githubUrl'] ?? null,
                'portfolio_url' => $data['portfolioUrl'] ?? null,
                'status' => TeamStatus::PendingActivation,
                'max_members_snapshot' => $settings->max_team_members,
                'version' => 1,
            ]);

            TeamMember::query()->create([
                'team_id' => $team->id,
                'user_id' => $leader->id,
                'role' => TeamMemberRole::Leader,
                'status' => TeamMemberStatus::Active,
                'joined_at' => $now,
                'invited_at' => $now,
            ]);

            TeamAgreement::query()->create([
                'team_id' => $team->id,
                'user_id' => $leader->id,
                'rules_policy_version' => $data['acceptedRulesVersion'],
                'data_usage_policy_version' => $data['acceptedDataUsageVersion'],
                'accepted_at' => $now,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            foreach ($members as $memberData) {
                $this->createInvitedMember($team, $leader, $memberData, $now);
            }

            $issued = $this->tokens->issue(
                OneTimeTokenPurpose::EmailActivation,
                $leader,
                $now->addHours((int) config('exoplanet.activation_ttl_hours', 24)),
            );

            $this->outbox->send($leader, new RegistrationReceived($code), ['team_code' => $code]);
            $this->outbox->send($leader, new AccountActivation($issued['token']), ['purpose' => 'activation']);
            $this->audit->write(AuditAction::TEAM_CREATE, $team, null, ['team_code' => $code], $leader);

            return [
                'teamId' => $team->id,
                'teamCode' => $code,
                'leaderUserId' => $leader->id,
                'activationRequired' => true,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $memberData
     */
    private function createInvitedMember(Team $team, User $leader, array $memberData, CarbonImmutable $now): void
    {
        $email = strtolower($memberData['email']);
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($user === null) {
            $user = User::query()->create([
                'first_name' => $memberData['firstName'],
                'last_name' => $memberData['lastName'],
                'email' => $email,
                'password' => Str::password(40),
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => null,
            ]);
            $user->assignRole('participant');
        }

        $member = TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamMemberRole::Member,
            'skill' => $memberData['skill'] ?? null,
            'status' => TeamMemberStatus::Invited,
            'invited_at' => $now,
        ]);

        $issued = $this->tokens->issue(
            OneTimeTokenPurpose::TeamInvitation,
            $user,
            $now->addDays((int) config('exoplanet.invitation_ttl_days', 7)),
            ['team_id' => $team->id, 'invitation' => true],
        );

        TeamInvitation::query()->create([
            'team_id' => $team->id,
            'team_member_id' => $member->id,
            'email' => $email,
            'invited_by_user_id' => $leader->id,
            'one_time_token_id' => $issued['model']->id,
            'status' => InvitationStatus::Pending,
        ]);
    }

    private function assertEmailAvailable(string $email): void
    {
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($user === null) {
            return;
        }
        if ($user->hasRole('judge') || $user->hasRole('admin')) {
            throw AppException::code('ACCOUNT_ROLE_CONFLICT', 409);
        }
        $onTeam = TeamMember::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [TeamMemberStatus::Invited, TeamMemberStatus::Active])
            ->exists();
        if ($onTeam) {
            throw AppException::code('EMAIL_IN_USE', 409);
        }
        if ($user->email_verified_at !== null) {
            throw AppException::code('EMAIL_IN_USE', 409);
        }
    }
}
