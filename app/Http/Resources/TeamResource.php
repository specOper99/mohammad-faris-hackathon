<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
final class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['members.user', 'track', 'leader']);

        return [
            'id' => $this->id,
            'teamCode' => $this->team_code,
            'name' => $this->name,
            'university' => $this->university,
            'organization' => $this->organization,
            'city' => $this->city,
            'country' => $this->country,
            'technicalLevel' => $this->technical_level,
            'trackId' => $this->track_id,
            'track' => $this->track ? [
                'id' => $this->track->id,
                'code' => $this->track->code,
                'nameEn' => $this->track->name_en,
                'nameAr' => $this->track->name_ar,
            ] : null,
            'githubUrl' => $this->github_url,
            'portfolioUrl' => $this->portfolio_url,
            'status' => $this->status->value,
            'rejectedReason' => $this->rejected_reason,
            'version' => $this->version,
            'leader' => [
                'id' => $this->leader?->id,
                'firstName' => $this->leader?->first_name,
                'lastName' => $this->leader?->last_name,
                'email' => $this->leader?->email,
            ],
            'members' => $this->members->map(fn ($m) => [
                'id' => $m->id,
                'userId' => $m->user_id,
                'role' => $m->role->value,
                'status' => $m->status->value,
                'skill' => $m->skill,
                'email' => $m->user?->email,
                'firstName' => $m->user?->first_name,
                'lastName' => $m->user?->last_name,
            ])->values()->all(),
            'createdAt' => optional($this->created_at)?->toIso8601ZuluString(),
        ];
    }
}
