<?php

namespace App\Actions\Files;

use App\Actions\Submissions\UpdateSubmissionAction;
use App\Enums\AuditAction;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Services\Storage\ObjectStorage;
use App\Support\AppException;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class DeleteFileAction
{
    public function __construct(
        private ObjectStorage $storage,
        private AuditLogger $audit,
        private UpdateSubmissionAction $mutable,
    ) {}

    public function execute(User $user, Submission $submission, string $fileId): void
    {
        if (! app(SubmissionPolicy::class)->update($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }
        $this->mutable->assertMutable($submission);

        $file = SubmissionFile::query()->where('submission_id', $submission->id)->where('id', $fileId)->first();
        if ($file === null) {
            throw AppException::code('FILE_NOT_FOUND', 404);
        }
        $file->is_current = false;
        $file->save();
        $this->storage->delete($file->storage_key);
        $this->audit->write(AuditAction::FILE_DELETE, $file, null, null, $user);
    }
}
