<?php

namespace App\Models;

use App\Enums\TrackDifficulty;
use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name_en',
    'name_ar',
    'description_en',
    'description_ar',
    'difficulty',
    'focus_en',
    'focus_ar',
    'is_active',
    'sort_order',
])]
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => TrackDifficulty::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
