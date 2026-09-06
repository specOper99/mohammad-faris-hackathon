<?php

namespace App\Actions\Auth;

use App\Enums\OneTimeTokenPurpose;
use App\Models\User;
use App\Notifications\PasswordReset;
use App\Support\Clock;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;

final class ForgotPasswordAction
{
    public function __construct(
        private OneTimeTokenService $tokens,
        private OutboxNotifier $outbox,
        private Clock $clock,
    ) {}

    public function execute(string $email): void
    {
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($email)])->first();
        if ($user === null || $user->email_verified_at === null) {
            return;
        }

        $this->tokens->invalidateUnused($user, OneTimeTokenPurpose::PasswordReset);
        $issued = $this->tokens->issue(
            OneTimeTokenPurpose::PasswordReset,
            $user,
            $this->clock->now()->addHours((int) config('exoplanet.password_reset_ttl_hours', 1)),
        );
        $this->outbox->send($user, new PasswordReset($issued['token']));
    }
}
