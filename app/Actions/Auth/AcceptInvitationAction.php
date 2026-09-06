<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Enums\InvitationStatus;
use App\Enums\OneTimeTokenPurpose;
use App\Enums\TeamMemberStatus;
use App\Models\TeamInvitation;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\OneTimeTokenService;
use Illuminate\Support\Facades\DB;

final class AcceptInvitationAction
{
    public function __construct(
        private OneTimeTokenService $tokens,
        private AuditLogger $audit,
    ) {}

    public function execute(string $rawToken, string $password, ?string $firstName, ?string $lastName): void
    {
        $token = $this->tokens->findValid($rawToken, OneTimeTokenPurpose::TeamInvitation);
        $invite = TeamInvitation::query()->where('one_time_token_id', $token->id)->first();
        if ($invite === null) {
            throw AppException::code('INVITATION_INVALID', 409);
        }
        if ($invite->status === InvitationStatus::Cancelled) {
            throw AppException::code('INVITATION_CANCELLED', 409);
        }
        if ($invite->status !== InvitationStatus::Pending) {
            throw AppException::code('INVITATION_INVALID', 409);
        }

        DB::transaction(function () use ($token, $invite, $password, $firstName, $lastName): void {
            $this->tokens->consume($token);
            $member = $invite->member;
            $user = $member?->user;
            if ($member === null || $user === null) {
                throw AppException::code('INVITATION_INVALID', 409);
            }
            if ($firstName) {
                $user->first_name = $firstName;
            }
            if ($lastName) {
                $user->last_name = $lastName;
            }
            $user->password = $password;
            $user->email_verified_at = now()->toImmutable();
            $user->is_active = true;
            $user->save();

            $member->status = TeamMemberStatus::Active;
            $member->joined_at = now()->toImmutable();
            $member->save();

            $invite->status = InvitationStatus::Accepted;
            $invite->accepted_at = now()->toImmutable();
            $invite->save();

            $this->audit->write(AuditAction::MEMBER_ACCEPT, $member, null, null, $user);
        });
    }
}
