<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'roles' => $this->getRoleNames()->values()->all(),
            'teamId' => $this->currentTeamId(),
            'emailVerifiedAt' => optional($this->email_verified_at)?->toIso8601ZuluString(),
        ];
    }
}
