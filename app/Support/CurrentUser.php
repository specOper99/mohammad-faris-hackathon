<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class CurrentUser
{
    public static function get(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function require(): User
    {
        $user = self::get();

        if (! $user instanceof User) {
            throw AppException::code('UNAUTHORIZED', 401);
        }

        return $user;
    }

    public static function id(): ?string
    {
        $user = auth()->user();

        return $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : null;
    }
}
