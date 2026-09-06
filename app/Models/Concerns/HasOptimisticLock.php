<?php

namespace App\Models\Concerns;

use App\Support\AppException;
use Illuminate\Database\Eloquent\Builder;

trait HasOptimisticLock
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveWithVersion(array $attributes = [], ?int $expectedVersion = null): void
    {
        if ($attributes !== []) {
            $this->fill($attributes);
        }

        $expected = $expectedVersion ?? (int) $this->getAttribute('version');
        $dirty = $this->getDirty();
        unset($dirty['version']);
        $dirty['version'] = $expected + 1;

        $updated = static::query()
            ->where($this->getKeyName(), $this->getKey())
            ->where('version', $expected)
            ->update($dirty);

        if ($updated === 0) {
            throw AppException::code('CONCURRENCY_CONFLICT', 409);
        }

        $this->refresh();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLockCurrent(Builder $query): Builder
    {
        return $query->lockForUpdate();
    }
}
