<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'team_member_id',
    'email',
    'invited_by_user_id',
    'one_time_token_id',
    'status',
    'accepted_at',
    'cancelled_at',
])]
/**
 * @property string $id
 * @property string $team_id
 * @property string $team_member_id
 * @property InvitationStatus $status
 * @property string $one_time_token_id
 * @property-read TeamMember $member
 * @property-read OneTimeToken|null $token
 */
class TeamInvitation extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'accepted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<TeamMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }

    /** @return BelongsTo<OneTimeToken, $this> */
    public function token(): BelongsTo
    {
        return $this->belongsTo(OneTimeToken::class, 'one_time_token_id');
    }
}
