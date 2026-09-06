<?php

namespace App\Models;

use App\Enums\FileScanStatus;
use App\Enums\FileType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'submission_id',
    'file_type',
    'original_file_name',
    'storage_key',
    'mime_type',
    'file_size',
    'checksum_sha256',
    'uploaded_by_user_id',
    'uploaded_at',
    'is_current',
    'scan_status',
    'upload_session_id',
])]
/**
 * @property string $id
 * @property FileType $file_type
 * @property FileScanStatus $scan_status
 */
class SubmissionFile extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => FileType::class,
            'scan_status' => FileScanStatus::class,
            'file_size' => 'integer',
            'is_current' => 'boolean',
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
