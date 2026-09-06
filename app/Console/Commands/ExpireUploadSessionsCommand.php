<?php

namespace App\Console\Commands;

use App\Enums\UploadSessionStatus;
use App\Models\UploadSession;
use App\Services\Storage\ObjectStorage;
use Illuminate\Console\Command;

final class ExpireUploadSessionsCommand extends Command
{
    protected $signature = 'exoplanet:expire-uploads';

    protected $description = 'Abort expired multipart uploads';

    public function handle(ObjectStorage $storage): int
    {
        $sessions = UploadSession::query()
            ->where('status', UploadSessionStatus::Initiated)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($sessions as $session) {
            if ($session->s3_upload_id) {
                try {
                    $storage->abortMultipart($session->storage_key, $session->s3_upload_id);
                } catch (\Throwable) {
                    // still mark expired
                }
            }
            $session->status = UploadSessionStatus::Expired;
            $session->save();
        }

        return self::SUCCESS;
    }
}
