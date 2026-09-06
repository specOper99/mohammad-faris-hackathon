<?php

namespace App\Services\Storage;

use Carbon\CarbonImmutable;

interface ObjectStorage
{
    /**
     * @return array{url: string, headers: array<string, string>, expiresAt: CarbonImmutable}
     */
    public function temporaryUploadUrl(string $key, string $contentType, int $minutes = 15): array;

    /**
     * @return array{uploadId: string, parts: list<array{partNumber: int, url: string}>, expiresAt: CarbonImmutable}
     */
    public function initiateMultipart(string $key, string $contentType, int $declaredSize, int $partSize): array;

    /**
     * @param  list<array{partNumber: int, eTag: string}>  $parts
     */
    public function completeMultipart(string $key, string $uploadId, array $parts): void;

    public function abortMultipart(string $key, string $uploadId): void;

    public function head(string $key): HeadResult;

    public function getPrefixBytes(string $key, int $length = 8): string;

    public function temporaryDownloadUrl(string $key, string $fileName, int $seconds = 60): string;

    public function delete(string $key): void;

    public function put(string $key, string $contents, string $contentType): void;

    public function bucketExists(): bool;
}
