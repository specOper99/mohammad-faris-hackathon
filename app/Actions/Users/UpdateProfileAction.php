<?php

namespace App\Actions\Users;

use App\Models\User;

final class UpdateProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): User
    {
        $user->fill([
            'first_name' => $data['firstName'] ?? $user->first_name,
            'last_name' => $data['lastName'] ?? $user->last_name,
            'phone' => $data['phone'] ?? $user->phone,
            'locale' => $data['locale'] ?? $user->locale,
        ]);
        $user->save();

        return $user;
    }
}
