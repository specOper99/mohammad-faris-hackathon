<?php

namespace App\Models;

use App\Enums\TeamStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasOptimisticLock;
use App\Models\Concerns\NotDeleted;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_code',
    'name',
    'leader_user_id',
    'university',
    'organization',
    'city',
    'country',
    'technical_level',
    'track_id',
    'github_url',
    'portfolio_url',
    'status',
    'rejected_reason',
    'max_members_snapshot',
    'version',
])]
/**
 * @property string $id
 * @property string $team_code
 * @property string $name
 * @property string $leader_user_id
 * @property string $track_id
 * @property TeamStatus $status
 * @property int $version
 * @property-read User|null $leader
 * @property-read Track|null $track
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use Auditable, HasFactory, HasOptimisticLock, HasUuids, NotDeleted, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TeamStatus::class,
            'is_deleted' => 'boolean',
            'version' => 'integer',
            'max_members_snapshot' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    /** @return BelongsTo<Track, $this> */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /** @return HasMany<TeamMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasMany<TeamInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** @return HasMany<TeamAgreement, $this> */
    public function agreements(): HasMany
    {
        return $this->hasMany(TeamAgreement::class);
    }

    /** @return HasOne<Submission, $this> */
    public function submission(): HasOne
    {
        return $this->hasOne(Submission::class);
    }
}
