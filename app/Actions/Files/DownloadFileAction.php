<?php

namespace App\Actions\Files;

use App\Enums\AuditAction;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Services\Storage\ObjectStorage;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\Clock;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class DownloadFileAction
{
    public function __construct(
        private ObjectStorage $storage,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @return array{url: string, expiresAt: string, fileName: string}
     */
    public function execute(User $user, Submission $submission, string $fileId): array
    {
        if (! app(SubmissionPolicy::class)->view($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        $file = SubmissionFile::query()->where('submission_id', $submission->id)->where('id', $fileId)->first();
        if ($file === null) {
            throw AppException::code('FILE_NOT_FOUND', 404);
        }

        $seconds = (int) config('exoplanet.download_seconds', 60);
        $url = $this->storage->temporaryDownloadUrl($file->storage_key, $file->original_file_name, $seconds);

        if ($user->hasRole(['judge', 'admin'])) {
            $this->audit->write(AuditAction::FILE_DOWNLOAD, $file, null, null, $user);
        }

        return [
            'url' => $url,
            'expiresAt' => $this->clock->now()->addSeconds($seconds)->toIso8601ZuluString(),
            'fileName' => $file->original_file_name,
        ];
    }
}
