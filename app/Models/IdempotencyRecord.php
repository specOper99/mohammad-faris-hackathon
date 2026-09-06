<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'user_id',
    'path',
    'body_hash',
    'status',
    'response_json',
])]
class IdempotencyRecord extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_json' => 'array',
            'status' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
