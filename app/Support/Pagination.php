<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Pagination
{
    public static function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    public static function pageSize(Request $request, int $default = 20): int
    {
        $size = (int) $request->query('pageSize', $default);

        return min(100, max(1, $size));
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
