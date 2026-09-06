<?php

namespace App\Actions\Auth;

use App\Enums\OneTimeTokenPurpose;
use App\Models\User;
use App\Notifications\AccountActivation;
use App\Support\Clock;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;

final class ResendActivationAction
{
    public function __construct(
        private OneTimeTokenService $tokens,
        private OutboxNotifier $outbox,
        private Clock $clock,
    ) {}

    public function execute(string $email): void
    {
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($email)])->first();
        if ($user === null || $user->email_verified_at !== null) {
            return;
        }

        $this->tokens->invalidateUnused($user, OneTimeTokenPurpose::EmailActivation);
        $issued = $this->tokens->issue(
            OneTimeTokenPurpose::EmailActivation,
            $user,
            $this->clock->now()->addHours((int) config('exoplanet.activation_ttl_hours', 24)),
        );
        $this->outbox->send($user, new AccountActivation($issued['token']));
    }
}
