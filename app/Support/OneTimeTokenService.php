<?php

namespace App\Support;

use App\Enums\OneTimeTokenPurpose;
use App\Models\OneTimeToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class OneTimeTokenService
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array{token: string, model: OneTimeToken}
     */
    public function issue(OneTimeTokenPurpose $purpose, ?User $user, CarbonImmutable $expiresAt, array $metadata = []): array
    {
        $raw = Str::random(64);
        $model = OneTimeToken::query()->create([
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'token_hash' => $this->hash($raw),
            'expires_at' => $expiresAt,
            'metadata' => $metadata,
        ]);

        return ['token' => $raw, 'model' => $model];
    }

    public function findValid(string $raw, OneTimeTokenPurpose $purpose): OneTimeToken
    {
        $token = OneTimeToken::query()
            ->where('purpose', $purpose)
            ->where('token_hash', $this->hash($raw))
            ->first();

        if ($token === null) {
            throw AppException::code($this->invalidCode($purpose), 409);
        }
        if ($token->consumed_at !== null) {
            throw AppException::code($this->consumedCode($purpose), 409);
        }
        if ($token->expires_at->isPast()) {
            throw AppException::code($this->expiredCode($purpose), 409);
        }

        return $token;
    }

    public function consume(OneTimeToken $token): void
    {
        $token->consumed_at = now()->toImmutable();
        $token->save();
    }

    public function invalidateUnused(User $user, OneTimeTokenPurpose $purpose): void
    {
        OneTimeToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    public function hash(string $raw): string
    {
        return hash('sha256', $raw, true);
    }

    private function invalidCode(OneTimeTokenPurpose $purpose): string
    {
        return match ($purpose) {
            OneTimeTokenPurpose::EmailActivation => 'ACTIVATION_INVALID',
            OneTimeTokenPurpose::PasswordReset => 'PASSWORD_RESET_INVALID',
            OneTimeTokenPurpose::TeamInvitation => 'INVITATION_INVALID',
        };
    }

    private function consumedCode(OneTimeTokenPurpose $purpose): string
    {
        return match ($purpose) {
            OneTimeTokenPurpose::EmailActivation => 'ACTIVATION_CONSUMED',
            OneTimeTokenPurpose::PasswordReset => 'PASSWORD_RESET_INVALID',
            OneTimeTokenPurpose::TeamInvitation => 'INVITATION_INVALID',
        };
    }

    private function expiredCode(OneTimeTokenPurpose $purpose): string
    {
        return match ($purpose) {
            OneTimeTokenPurpose::EmailActivation => 'ACTIVATION_EXPIRED',
            OneTimeTokenPurpose::PasswordReset => 'PASSWORD_RESET_INVALID',
            OneTimeTokenPurpose::TeamInvitation => 'INVITATION_EXPIRED',
        };
    }
}
