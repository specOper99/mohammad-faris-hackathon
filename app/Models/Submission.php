<?php

namespace App\Models;

use App\Enums\LockReason;
use App\Enums\PublicationStatus;
use App\Enums\SubmissionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasOptimisticLock;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'submission_code',
    'team_id',
    'track_id',
    'project_name',
    'abstract',
    'problem_description',
    'solution_description',
    'github_url',
    'demo_url',
    'limitations',
    'ai_usage',
    'readme_inline',
    'status',
    'submitted_at',
    'first_submitted_at',
    'locked_at',
    'lock_reason',
    'reopen_count',
    'reopened_at',
    'reopened_by_user_id',
    'publication_status',
    'aggregated_score',
    'version',
    'created_by_user_id',
    'updated_by_user_id',
])]
/**
 * @property string $id
 * @property string|null $submission_code
 * @property string $team_id
 * @property string $track_id
 * @property SubmissionStatus $status
 * @property LockReason|null $lock_reason
 * @property PublicationStatus $publication_status
 * @property int $version
 * @property-read Team $team
 * @property-read Track $track
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use Auditable, HasFactory, HasOptimisticLock, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'lock_reason' => LockReason::class,
            'publication_status' => PublicationStatus::class,
            'submitted_at' => 'immutable_datetime',
            'first_submitted_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'aggregated_score' => 'decimal:2',
            'reopen_count' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Track, $this> */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /** @return HasMany<SubmissionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /** @return HasMany<SubmissionFile, $this> */
    public function currentFiles(): HasMany
    {
        return $this->hasMany(SubmissionFile::class)->where('is_current', true);
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /** @return HasMany<JudgeAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
