<?php

namespace App\Actions\Auth;

use App\Enums\OneTimeTokenPurpose;
use App\Support\OneTimeTokenService;
use Illuminate\Support\Facades\DB;

final class ResetPasswordAction
{
    public function __construct(private OneTimeTokenService $tokens) {}

    public function execute(string $rawToken, string $password): void
    {
        $token = $this->tokens->findValid($rawToken, OneTimeTokenPurpose::PasswordReset);
        $user = $token->user;
        if ($user === null) {
            return;
        }

        DB::transaction(function () use ($token, $user, $password): void {
            $this->tokens->consume($token);
            $user->password = $password;
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}
