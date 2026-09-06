<?php

namespace App\Models;

use App\Enums\EvaluationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'submission_id',
    'judge_profile_id',
    'status',
    'total_score',
    'comments',
    'submitted_at',
    'reopened_at',
    'reopened_by_user_id',
    'version',
])]
/**
 * @property string $id
 * @property string $submission_id
 * @property string $judge_profile_id
 * @property EvaluationStatus $status
 * @property-read Submission $submission
 */
class Evaluation extends Model
{
    use Auditable, HasOptimisticLock, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EvaluationStatus::class,
            'total_score' => 'decimal:2',
            'submitted_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<JudgeProfile, $this> */
    public function judgeProfile(): BelongsTo
    {
        return $this->belongsTo(JudgeProfile::class);
    }

    /** @return HasMany<EvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
