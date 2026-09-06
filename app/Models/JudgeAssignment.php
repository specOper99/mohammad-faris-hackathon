<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'judge_profile_id',
    'submission_id',
    'assigned_at',
    'assigned_by_user_id',
    'unassigned_at',
])]
class JudgeAssignment extends Model
{
    use Auditable, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'unassigned_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<JudgeProfile, $this> */
    public function judgeProfile(): BelongsTo
    {
        return $this->belongsTo(JudgeProfile::class);
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
