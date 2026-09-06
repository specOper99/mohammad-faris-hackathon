<?php

namespace App\Actions\Files;

use App\Enums\UploadSessionStatus;
use App\Models\Submission;
use App\Models\UploadSession;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Services\Storage\ObjectStorage;
use App\Support\AppException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class AbortUploadAction
{
    public function __construct(private ObjectStorage $storage) {}

    public function execute(User $user, Submission $submission, string $sessionId): void
    {
        if (! app(SubmissionPolicy::class)->update($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        $session = UploadSession::query()
            ->where('id', $sessionId)
            ->where('submission_id', $submission->id)
            ->first();
        if ($session === null) {
            throw AppException::code('UPLOAD_SESSION_INVALID', 409);
        }
        if ($session->s3_upload_id) {
            $this->storage->abortMultipart($session->storage_key, $session->s3_upload_id);
        }
        $session->status = UploadSessionStatus::Aborted;
        $session->save();
    }
}
