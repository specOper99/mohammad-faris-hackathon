<?php

namespace App\Models;

use App\Enums\TeamMemberStatus;
use App\Models\Concerns\NotDeleted;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'phone',
    'password',
    'locale',
    'email_verified_at',
    'is_active',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property CarbonImmutable|null $email_verified_at
 * @property bool $is_active
 * @property bool $is_deleted
 * @property string $locale
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, NotDeleted, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    /** @return HasMany<TeamMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasOne<TeamMember, $this> */
    public function currentMembership(): HasOne
    {
        return $this->hasOne(TeamMember::class)->whereIn('status', [
            TeamMemberStatus::Invited->value,
            TeamMemberStatus::Active->value,
        ]);
    }

    /** @return HasOne<JudgeProfile, $this> */
    public function judgeProfile(): HasOne
    {
        return $this->hasOne(JudgeProfile::class);
    }

    public function currentTeam(): ?Team
    {
        $membership = $this->currentMembership()->with('team')->first();

        return $membership?->team;
    }

    public function currentTeamId(): ?string
    {
        return $this->currentTeam()?->id;
    }
}
