<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AppException;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class LoginAction
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{user: array<string, mixed>}
     */
    public function execute(string $email, string $password, string $ip): array
    {
        $lockKey = 'login-lockout:'.Str::lower($email).'|'.$ip;
        if (RateLimiter::tooManyAttempts($lockKey, 5)) {
            throw AppException::code('ACCOUNT_LOCKED', 401);
        }

        $user = User::query()->withDeleted()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($lockKey, 15 * 60);
            throw AppException::code('INVALID_CREDENTIALS', 401);
        }

        if ($user->is_deleted) {
            throw AppException::code('ACCOUNT_DELETED', 401);
        }
        if (! $user->is_active) {
            throw AppException::code('ACCOUNT_INACTIVE', 401);
        }
        if ($user->email_verified_at === null) {
            throw AppException::code('ACCOUNT_NOT_ACTIVATED', 401);
        }

        RateLimiter::clear($lockKey);
        Auth::login($user);
        request()->session()->regenerate();
        $user->last_login_at = now()->toImmutable();
        $user->save();
        $this->audit->write(AuditAction::AUTH_LOGIN, $user, null, ['email' => $user->email], $user);

        return ['user' => (new UserResource($user->load('roles')))->resolve()];
    }
}
