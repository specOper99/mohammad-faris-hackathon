<?php

namespace App\Services\Storage;

use Illuminate\Support\Str;

final class FakeObjectStorage implements ObjectStorage
{
    /** @var array<string, string> */
    private array $objects = [];

    /** @var array<string, array{key: string, parts: array<int, string>}> */
    private array $multiparts = [];

    public function temporaryUploadUrl(string $key, string $contentType, int $minutes = 15): array
    {
        return [
            'url' => 'https://s3.test/put/'.$key,
            'headers' => ['Content-Type' => $contentType],
            'expiresAt' => now()->toImmutable()->addMinutes($minutes),
        ];
    }

    public function initiateMultipart(string $key, string $contentType, int $declaredSize, int $partSize): array
    {
        $uploadId = (string) Str::uuid();
        $this->multiparts[$uploadId] = ['key' => $key, 'parts' => []];
        $partCount = max(1, (int) ceil($declaredSize / $partSize));
        $expiresAt = now()->toImmutable()->addMinutes((int) config('exoplanet.presign_minutes', 15));
        $parts = [];
        for ($i = 1; $i <= $partCount; $i++) {
            $parts[] = [
                'partNumber' => $i,
                'url' => 'https://s3.test/part/'.$uploadId.'/'.$i,
            ];
        }

        return ['uploadId' => $uploadId, 'parts' => $parts, 'expiresAt' => $expiresAt];
    }

    public function completeMultipart(string $key, string $uploadId, array $parts): void
    {
        $session = $this->multiparts[$uploadId] ?? ['key' => $key, 'parts' => []];
        $this->objects[$key] = implode('', $session['parts']);
        unset($this->multiparts[$uploadId]);
    }

    public function abortMultipart(string $key, string $uploadId): void
    {
        unset($this->multiparts[$uploadId]);
    }

    public function head(string $key): HeadResult
    {
        $body = $this->objects[$key] ?? '';

        return new HeadResult(strlen($body), null, '"fake"');
    }

    public function getPrefixBytes(string $key, int $length = 8): string
    {
        return substr($this->objects[$key] ?? '', 0, $length);
    }

    public function temporaryDownloadUrl(string $key, string $fileName, int $seconds = 60): string
    {
        return 'https://s3.test/download/'.$key.'?name='.rawurlencode($fileName).'&exp='.$seconds;
    }

    public function delete(string $key): void
    {
        unset($this->objects[$key]);
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        $this->objects[$key] = $contents;
    }

    public function putPart(string $uploadId, int $partNumber, string $contents): void
    {
        $this->multiparts[$uploadId]['parts'][$partNumber] = $contents;
    }

    public function bucketExists(): bool
    {
        return true;
    }
}
