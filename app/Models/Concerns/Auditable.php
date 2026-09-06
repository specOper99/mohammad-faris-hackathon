<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\CurrentUser;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::updated(function (self $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at'], $changes['version'], $changes['password'], $changes['remember_token']);
            if ($changes === []) {
                return;
            }
            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }
            AuditLog::query()->create([
                'user_id' => CurrentUser::id(),
                'action' => class_basename($model).'.Update',
                'entity_type' => $model->getMorphClass(),
                'entity_id' => $model->getKey(),
                'old_value' => $original,
                'new_value' => $changes,
                'ip_address' => app()->runningInConsole() ? 'system' : request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'correlation_id' => request()?->attributes->get('correlation_id'),
            ]);
        });

        static::deleted(function (self $model): void {
            AuditLog::query()->create([
                'user_id' => CurrentUser::id(),
                'action' => class_basename($model).'.Delete',
                'entity_type' => $model->getMorphClass(),
                'entity_id' => $model->getKey(),
                'old_value' => $model->toArray(),
                'new_value' => null,
                'ip_address' => app()->runningInConsole() ? 'system' : request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'correlation_id' => request()?->attributes->get('correlation_id'),
            ]);
        });
    }
}
