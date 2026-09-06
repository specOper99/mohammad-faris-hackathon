<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function write(
        string $action,
        ?Model $entity,
        ?array $old = null,
        ?array $new = null,
        ?User $user = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $user !== null ? $user->id : CurrentUser::id(),
            'action' => $action,
            'entity_type' => $entity !== null ? $entity->getMorphClass() : 'system',
            'entity_id' => $entity?->getKey(),
            'old_value' => $this->scrub($old),
            'new_value' => $this->scrub($new),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'correlation_id' => request()?->attributes->get('correlation_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function scrub(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        foreach (['password', 'token', 'token_hash', 'remember_token'] as $secret) {
            unset($payload[$secret]);
        }

        return $payload;
    }
}
