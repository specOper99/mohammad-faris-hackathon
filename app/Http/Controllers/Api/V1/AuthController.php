<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\AcceptInvitationAction;
use App\Actions\Auth\ActivateAccountAction;
use App\Actions\Auth\ForgotPasswordAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\LogoutAllAction;
use App\Actions\Auth\RegisterTeamAction;
use App\Actions\Auth\ResendActivationAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Auth\ActivateAccountRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTeamRequest;
use App\Http\Requests\Auth\ResendActivationRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Auth', weight: 1)]
final class AuthController extends Controller
{
    public function register(RegisterTeamRequest $request, RegisterTeamAction $action): JsonResponse
    {
        return ApiResponse::created($action->execute($request->toData()));
    }

    public function activate(ActivateAccountRequest $request, ActivateAccountAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute((string) $request->validated('token')));
    }

    public function resendActivation(ResendActivationRequest $request, ResendActivationAction $action): JsonResponse
    {
        $action->execute((string) $request->validated('email'));

        return ApiResponse::success(['ok' => true]);
    }

    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $user = $action->execute(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            (string) $request->ip(),
        );

        return ApiResponse::success(['user' => new UserResource($user)]);
    }

    #[Endpoint(description: 'Destroy the current Sanctum session cookie. No request body.')]
    public function logout(LogoutAction $action): Response
    {
        $action->execute();

        return ApiResponse::noContent();
    }

    #[Endpoint(description: 'Destroy all sessions for the current user. No request body.')]
    public function logoutAll(LogoutAllAction $action): Response
    {
        $action->execute(CurrentUser::require());

        return ApiResponse::noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request, ForgotPasswordAction $action): JsonResponse
    {
        $action->execute((string) $request->validated('email'));

        return ApiResponse::success(['ok' => true]);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $data = $request->validated();
        $action->execute((string) $data['token'], (string) $data['password']);

        return ApiResponse::success(['ok' => true]);
    }

    public function acceptInvitation(AcceptInvitationRequest $request, AcceptInvitationAction $action): JsonResponse
    {
        $data = $request->validated();
        $action->execute(
            (string) $data['token'],
            (string) $data['password'],
            isset($data['firstName']) ? (string) $data['firstName'] : null,
            isset($data['lastName']) ? (string) $data['lastName'] : null,
        );

        return ApiResponse::success(['ok' => true]);
    }
}
