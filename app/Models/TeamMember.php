<?php

namespace App\Models;

use App\Enums\TeamMemberRole;
use App\Enums\TeamMemberStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'user_id',
    'role',
    'skill',
    'status',
    'joined_at',
    'invited_at',
    'removed_at',
])]
/**
 * @property string $id
 * @property string $team_id
 * @property string $user_id
 * @property TeamMemberRole $role
 * @property TeamMemberStatus $status
 * @property-read Team $team
 * @property-read User $user
 */
class TeamMember extends Model
{
    use Auditable, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamMemberRole::class,
            'status' => TeamMemberStatus::class,
            'joined_at' => 'immutable_datetime',
            'invited_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
