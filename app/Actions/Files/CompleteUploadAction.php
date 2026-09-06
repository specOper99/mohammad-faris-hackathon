<?php

namespace App\Actions\Files;

use App\Actions\Submissions\UpdateSubmissionAction;
use App\Domain\Files\FilePolicy;
use App\Enums\AuditAction;
use App\Enums\FileScanStatus;
use App\Enums\FileType;
use App\Enums\UploadSessionStatus;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\UploadSession;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Services\Scan\MalwareScanner;
use App\Services\Storage\ObjectStorage;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\Clock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class CompleteUploadAction
{
    public function __construct(
        private ObjectStorage $storage,
        private FilePolicy $policy,
        private MalwareScanner $scanner,
        private AuditLogger $audit,
        private Clock $clock,
        private UpdateSubmissionAction $mutable,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, Submission $submission, string $sessionId, array $data): SubmissionFile
    {
        if (! app(SubmissionPolicy::class)->update($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }
        $this->mutable->assertMutable($submission);

        $session = UploadSession::query()
            ->where('id', $sessionId)
            ->where('submission_id', $submission->id)
            ->first();
        if ($session === null || $session->status !== UploadSessionStatus::Initiated) {
            throw AppException::code('UPLOAD_SESSION_INVALID', 409);
        }
        if ($session->expires_at->lt($this->clock->now())) {
            throw AppException::code('UPLOAD_SESSION_EXPIRED', 409);
        }

        if ($session->s3_upload_id) {
            $parts = $data['parts'] ?? [];
            $this->storage->completeMultipart($session->storage_key, $session->s3_upload_id, $parts);
        }

        $head = $this->storage->head($session->storage_key);
        if ($head->size !== $session->declared_size) {
            throw AppException::code('FILE_CONTENT_MISMATCH', 422);
        }
        if ($head->contentType && strtolower($head->contentType) !== strtolower($session->declared_mime)) {
            // MinIO may omit; only fail when present and different, except zip octet-stream.
            if (! ($session->file_type === FileType::ArchiveZip && strtolower((string) $head->contentType) === 'application/octet-stream')) {
                throw AppException::code('FILE_CONTENT_MISMATCH', 422);
            }
        }

        $prefix = $this->storage->getPrefixBytes($session->storage_key, 8);
        if (! $this->policy->matchesMagicBytes($session->file_type, $prefix)) {
            throw AppException::code('FILE_CONTENT_MISMATCH', 422);
        }

        $scan = FileScanStatus::from($this->scanner->scan($session->storage_key));

        return DB::transaction(function () use ($user, $submission, $session, $scan) {
            SubmissionFile::query()
                ->where('submission_id', $submission->id)
                ->where('file_type', $session->file_type)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $file = SubmissionFile::query()->create([
                'submission_id' => $submission->id,
                'file_type' => $session->file_type,
                'original_file_name' => $session->original_file_name,
                'storage_key' => $session->storage_key,
                'mime_type' => $session->declared_mime,
                'file_size' => $session->declared_size,
                'uploaded_by_user_id' => $user->id,
                'uploaded_at' => $this->clock->now(),
                'is_current' => true,
                'scan_status' => $scan,
                'upload_session_id' => $session->id,
            ]);

            $session->status = UploadSessionStatus::Completed;
            $session->completed_at = $this->clock->now();
            $session->save();

            $this->audit->write(AuditAction::FILE_COMPLETE, $file, null, ['type' => $session->file_type->value], $user);

            return $file;
        });
    }
}
