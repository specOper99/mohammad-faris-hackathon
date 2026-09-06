<?php

namespace App\Support;

final class HttpsUrl
{
    public static function isValid(?string $url, bool $forbidLocalhost = false): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || str_starts_with($url, 'javascript:') || str_starts_with($url, 'data:')) {
            return false;
        }

        if ($forbidLocalhost && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return true;
    }
}
