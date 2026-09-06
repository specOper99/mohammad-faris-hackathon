<?php

namespace App\Models;

use App\Enums\FileType;
use App\Enums\UploadSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'submission_id',
    'user_id',
    'file_type',
    'storage_key',
    's3_upload_id',
    'declared_size',
    'declared_mime',
    'original_file_name',
    'status',
    'part_size',
    'expires_at',
    'completed_at',
])]
/**
 * @property string $id
 * @property FileType $file_type
 * @property UploadSessionStatus $status
 */
class UploadSession extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => FileType::class,
            'status' => UploadSessionStatus::class,
            'declared_size' => 'integer',
            'part_size' => 'integer',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
