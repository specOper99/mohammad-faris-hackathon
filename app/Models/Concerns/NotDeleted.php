<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait NotDeleted
{
    public static function bootNotDeleted(): void
    {
        static::addGlobalScope('not_deleted', function (Builder $builder): void {
            $builder->where($builder->getModel()->getTable().'.is_deleted', false);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithDeleted(Builder $query): Builder
    {
        return $query->withoutGlobalScope('not_deleted');
    }
}
