<?php

namespace App\Models;

use App\Enums\EmailOutboxStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'template_key',
    'to_email',
    'payload',
    'status',
    'attempt_count',
    'next_attempt_at',
    'last_error',
    'sent_at',
    'idempotency_key',
    'notification_id',
])]
class EmailOutbox extends Model
{
    use HasUuids;

    protected $table = 'email_outbox';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailOutboxStatus::class,
            'payload' => 'array',
            'attempt_count' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
