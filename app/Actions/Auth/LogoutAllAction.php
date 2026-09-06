<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class LogoutAllAction
{
    public function __construct(private AuditLogger $audit) {}

    public function execute(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
        $this->audit->write(AuditAction::AUTH_LOGOUT, $user, null, ['all' => true], $user);
    }
}
