<?php

namespace App\Models;

use App\Enums\OneTimeTokenPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'purpose',
    'token_hash',
    'expires_at',
    'consumed_at',
    'metadata',
])]
/**
 * @property string $id
 * @property string|null $user_id
 * @property OneTimeTokenPurpose $purpose
 * @property-read User|null $user
 */
class OneTimeToken extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OneTimeTokenPurpose::class,
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
