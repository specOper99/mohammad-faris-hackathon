<?php

namespace App\Http\Resources;

use App\Models\Track;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Track */
final class TrackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'nameEn' => $this->name_en,
            'nameAr' => $this->name_ar,
            'descriptionEn' => $this->description_en,
            'descriptionAr' => $this->description_ar,
            'difficulty' => $this->difficulty->value,
            'focusEn' => $this->focus_en,
            'focusAr' => $this->focus_ar,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
        ];
    }
}
