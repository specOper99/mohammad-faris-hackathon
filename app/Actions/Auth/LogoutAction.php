<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;

final class LogoutAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(): void
    {
        $user = Auth::guard('web')->user() ?? Auth::user();
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
        if ($user) {
            $this->audit->write(AuditAction::AUTH_LOGOUT, $user, null, null, $user);
        }
    }
}
