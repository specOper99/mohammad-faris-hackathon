<?php

namespace App\Domain\Files;

use App\Support\AppException;

final class FilenameSanitizer
{
    public function sanitize(string $original): string
    {
        $name = basename(str_replace('\\', '/', $original));
        $name = preg_replace('/[\\\\\/:\*\?"<>|]/', '_', $name) ?? $name;
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }
        if (strlen($name) > 200) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $keep = 200 - ($ext !== '' ? strlen($ext) + 1 : 0);
            $name = substr($base, 0, max(1, $keep)).($ext !== '' ? '.'.$ext : '');
        }

        return $name;
    }

    public function assertValid(string $original): void
    {
        if ($original === '' || strlen($original) > 200 || preg_match('/[\\\\\/:\*\?"<>|]/', $original) === 1) {
            throw AppException::code('FILE_NAME_INVALID', 422);
        }
    }
}
