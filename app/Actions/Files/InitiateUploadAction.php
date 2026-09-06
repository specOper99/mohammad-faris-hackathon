<?php

namespace App\Actions\Files;

use App\Actions\Submissions\UpdateSubmissionAction;
use App\Domain\Files\FilenameSanitizer;
use App\Domain\Files\FilePolicy;
use App\Enums\FileType;
use App\Enums\UploadSessionStatus;
use App\Models\ChallengeSettings;
use App\Models\Submission;
use App\Models\UploadSession;
use App\Models\User;
use App\Policies\SubmissionPolicy;
use App\Services\Storage\ObjectStorage;
use App\Support\AppException;
use App\Support\Clock;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class InitiateUploadAction
{
    public function __construct(
        private ObjectStorage $storage,
        private FilePolicy $policy,
        private FilenameSanitizer $names,
        private Clock $clock,
        private UpdateSubmissionAction $mutable,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(User $user, Submission $submission, array $data): array
    {
        if (! app(SubmissionPolicy::class)->update($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }
        $this->mutable->assertMutable($submission);

        $type = FileType::tryFrom((string) $data['fileType']);
        if ($type === null) {
            throw AppException::code('FILE_TYPE_NOT_ALLOWED', 422);
        }

        $fileName = (string) $data['fileName'];
        $this->names->assertValid($fileName);
        $safe = $this->names->sanitize($fileName);
        $mime = strtolower((string) $data['mimeType']);
        $size = (int) $data['sizeBytes'];
        $settings = ChallengeSettings::current();
        $this->policy->assert($type, $fileName, $mime, $size, $settings->file_policy ?? config('exoplanet.file_policy'));

        $session = UploadSession::query()->create([
            'submission_id' => $submission->id,
            'user_id' => $user->id,
            'file_type' => $type,
            'storage_key' => '',
            'declared_size' => $size,
            'declared_mime' => $mime,
            'original_file_name' => $safe,
            'status' => UploadSessionStatus::Initiated,
            'part_size' => $data['partSizeBytes'] ?? config('exoplanet.part_size_bytes'),
            'expires_at' => $this->clock->now()->addMinutes((int) config('exoplanet.presign_minutes', 15)),
        ]);

        $key = sprintf(
            '%s/teams/%s/submissions/%s/%s/%s/%s',
            app()->environment(),
            $submission->team_id,
            $submission->id,
            $type->value,
            $session->id,
            $safe,
        );
        $session->storage_key = $key;

        $threshold = (int) config('exoplanet.multipart_threshold', 8_388_608);
        if ($size >= $threshold || $type === FileType::ArchiveZip) {
            $partSize = (int) ($data['partSizeBytes'] ?? config('exoplanet.part_size_bytes'));
            $multi = $this->storage->initiateMultipart($key, $mime, $size, $partSize);
            $session->s3_upload_id = $multi['uploadId'];
            $session->part_size = $partSize;
            $session->save();

            return [
                'uploadSessionId' => $session->id,
                'storageKey' => $key,
                'mode' => 'multipart',
                'parts' => $multi['parts'],
                'expiresAt' => $multi['expiresAt']->toIso8601ZuluString(),
                'headers' => ['Content-Type' => $mime],
            ];
        }

        $single = $this->storage->temporaryUploadUrl($key, $mime, (int) config('exoplanet.presign_minutes', 15));
        $session->save();

        return [
            'uploadSessionId' => $session->id,
            'storageKey' => $key,
            'mode' => 'single',
            'url' => $single['url'],
            'expiresAt' => $single['expiresAt']->toIso8601ZuluString(),
            'headers' => $single['headers'],
        ];
    }
}
