<?php

namespace App\Services\Storage;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

final class S3ObjectStorage implements ObjectStorage
{
    public function temporaryUploadUrl(string $key, string $contentType, int $minutes = 15): array
    {
        $expiresAt = now()->toImmutable()->addMinutes($minutes);
        $disk = Storage::disk('s3');
        $url = $disk->temporaryUploadUrl($key, $expiresAt, [
            'ContentType' => $contentType,
        ]);

        if (is_array($url)) {
            return [
                'url' => $this->rewritePublicHost((string) ($url['url'] ?? '')),
                'headers' => $url['headers'] ?? ['Content-Type' => $contentType],
                'expiresAt' => $expiresAt,
            ];
        }

        return [
            'url' => $this->rewritePublicHost((string) $url),
            'headers' => ['Content-Type' => $contentType],
            'expiresAt' => $expiresAt,
        ];
    }

    public function initiateMultipart(string $key, string $contentType, int $declaredSize, int $partSize): array
    {
        $client = $this->client();
        $bucket = $this->bucket();
        $created = $client->createMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);
        $uploadId = (string) $created->get('UploadId');
        $partCount = max(1, (int) ceil($declaredSize / max(1, $partSize)));
        $expiresAt = now()->toImmutable()->addMinutes((int) config('exoplanet.presign_minutes', 15));
        $parts = [];
        for ($n = 1; $n <= $partCount; $n++) {
            $cmd = $client->getCommand('UploadPart', [
                'Bucket' => $bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumber' => $n,
            ]);
            $request = $client->createPresignedRequest($cmd, $expiresAt);
            $parts[] = [
                'partNumber' => $n,
                'url' => $this->rewritePublicHost((string) $request->getUri()),
            ];
        }

        return ['uploadId' => $uploadId, 'parts' => $parts, 'expiresAt' => $expiresAt];
    }

    public function completeMultipart(string $key, string $uploadId, array $parts): void
    {
        $this->client()->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => array_map(fn (array $part): array => [
                    'PartNumber' => $part['partNumber'],
                    'ETag' => $part['eTag'],
                ], $parts),
            ],
        ]);
    }

    public function abortMultipart(string $key, string $uploadId): void
    {
        $this->client()->abortMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }

    public function head(string $key): HeadResult
    {
        $result = $this->client()->headObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]);

        return new HeadResult(
            (int) $result->get('ContentLength'),
            $result->get('ContentType'),
            $result->get('ETag'),
        );
    }

    public function getPrefixBytes(string $key, int $length = 8): string
    {
        $result = $this->client()->getObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'Range' => 'bytes=0-'.max(0, $length - 1),
        ]);
        $body = $result->get('Body');

        return is_object($body) && method_exists($body, '__toString') ? (string) $body : (string) $body;
    }

    public function temporaryDownloadUrl(string $key, string $fileName, int $seconds = 60): string
    {
        $expires = now()->addSeconds($seconds);
        $url = Storage::disk('s3')->temporaryUrl($key, $expires, [
            'ResponseContentDisposition' => 'attachment; filename="'.str_replace('"', '', $fileName).'"',
        ]);

        return $this->rewritePublicHost($url);
    }

    public function delete(string $key): void
    {
        Storage::disk('s3')->delete($key);
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        Storage::disk('s3')->put($key, $contents, ['ContentType' => $contentType]);
    }

    public function bucketExists(): bool
    {
        try {
            $this->client()->headBucket(['Bucket' => $this->bucket()]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function client(): S3Client
    {
        $cfg = config('filesystems.disks.s3');
        $endpoint = $cfg['endpoint'] ?? null;

        return new S3Client([
            'version' => 'latest',
            'region' => $cfg['region'] ?? 'us-east-1',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => (bool) ($cfg['use_path_style_endpoint'] ?? false),
            'credentials' => [
                'key' => $cfg['key'] ?? '',
                'secret' => $cfg['secret'] ?? '',
            ],
        ]);
    }

    private function bucket(): string
    {
        return (string) config('filesystems.disks.s3.bucket');
    }

    private function rewritePublicHost(string $url): string
    {
        $public = rtrim((string) (config('filesystems.disks.s3.url') ?: ''), '/');
        $internal = rtrim((string) (config('filesystems.disks.s3.endpoint') ?: ''), '/');
        if ($public !== '' && $internal !== '' && str_contains($url, $internal)) {
            return str_replace($internal, $public, $url);
        }

        return $url;
    }
}
