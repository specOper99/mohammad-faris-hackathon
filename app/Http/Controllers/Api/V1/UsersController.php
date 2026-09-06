<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\ChangePasswordAction;
use App\Actions\Users\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\PasswordRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UsersController extends Controller
{
    public function me(): JsonResponse
    {
        $user = CurrentUser::require()->load('roles');

        return ApiResponse::success((new UserResource($user))->resolve());
    }

    public function updateMe(Request $request, UpdateProfileAction $action): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['sometimes', 'string', 'max:100'],
            'lastName' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^\+[0-9]{8,15}$/'],
            'locale' => ['sometimes', 'in:en,ar'],
        ]);
        $user = $action->execute(CurrentUser::require(), $data);

        return ApiResponse::success((new UserResource($user->load('roles')))->resolve());
    }

    public function updatePassword(Request $request, ChangePasswordAction $action): JsonResponse
    {
        $data = $request->validate([
            'current' => ['required', 'string'],
            'new' => array_merge(PasswordRules::rules(), ['same:confirm']),
            'confirm' => ['required'],
        ]);
        $action->execute(CurrentUser::require(), $data);

        return ApiResponse::success(['ok' => true]);
    }
}
