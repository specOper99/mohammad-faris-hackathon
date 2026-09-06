<?php

namespace App\Http\Middleware;

use App\Support\AppException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->is_deleted) {
            throw AppException::code('ACCOUNT_DELETED', 401);
        }
        if (! $user->is_active) {
            throw AppException::code('ACCOUNT_INACTIVE', 401);
        }
        if ($user->hasRole('participant') && $user->email_verified_at === null) {
            throw AppException::code('ACCOUNT_NOT_ACTIVATED', 401);
        }

        return $next($request);
    }
}
