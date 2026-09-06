<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\ChangePasswordAction;
use App\Actions\Users\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Users', weight: 4)]
final class UsersController extends Controller
{
    public function me(): JsonResponse
    {
        $user = CurrentUser::require()->load('roles');

        return ApiResponse::success((new UserResource($user))->resolve());
    }

    public function updateMe(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        $user = $action->execute(CurrentUser::require(), $request->validated());

        return ApiResponse::success((new UserResource($user->load('roles')))->resolve());
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $request->validated());

        return ApiResponse::success(['ok' => true]);
    }
}
