<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\AppException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ChangePasswordAction
{
    /**
     * @param  array{current: string, new: string}  $data
     */
    public function execute(User $user, array $data): void
    {
        if (! Hash::check($data['current'], $user->password)) {
            throw AppException::code('INVALID_CREDENTIALS', 401);
        }

        $user->password = $data['new'];
        $user->save();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', request()->session()->getId())->delete();
    }
}
