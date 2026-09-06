<?php

namespace App\Models;

use Database\Factories\JudgeProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'specialization',
    'is_active',
    'created_by_user_id',
])]
/**
 * @property string $id
 * @property string $user_id
 * @property bool $is_active
 * @property-read User $user
 */
class JudgeProfile extends Model
{
    /** @use HasFactory<JudgeProfileFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<JudgeAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class);
    }
}
