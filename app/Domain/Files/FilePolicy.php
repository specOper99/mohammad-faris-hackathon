<?php

namespace App\Domain\Files;

use App\Enums\FileType;
use App\Support\AppException;

final class FilePolicy
{
    /**
     * @param  array<string, mixed>  $policy
     */
    public function assert(FileType $type, string $fileName, string $mimeType, int $sizeBytes, array $policy): void
    {
        $ext = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = $policy['allowedExtensionsByType'][$type->value] ?? [];
        $allowedMime = $policy['allowedMimesByType'][$type->value] ?? [];
        $maxBytes = (int) ($policy['maxBytesByType'][$type->value] ?? 0);

        if ($allowedExt === [] || ! in_array($ext, $allowedExt, true)) {
            throw AppException::code('FILE_EXTENSION_INVALID', 422);
        }

        $mime = strtolower($mimeType);
        if ($mime === 'application/octet-stream' && $type !== FileType::ArchiveZip) {
            throw AppException::code('FILE_MIME_INVALID', 422);
        }

        if ($allowedMime === [] || ! in_array($mime, $allowedMime, true)) {
            throw AppException::code('FILE_MIME_INVALID', 422);
        }

        if ($maxBytes > 0 && $sizeBytes > $maxBytes) {
            throw AppException::code('FILE_TOO_LARGE', 422);
        }

        if ($sizeBytes < 1) {
            throw AppException::code('FILE_TOO_LARGE', 422);
        }
    }

    public function matchesMagicBytes(FileType $type, string $bytes): bool
    {
        return match ($type) {
            FileType::ReportPdf => str_starts_with($bytes, '%PDF'),
            FileType::ArchiveZip => str_starts_with($bytes, 'PK'),
            default => true,
        };
    }
}
