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
use App\Http\Requests\Auth\RegisterTeamRequest;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\PasswordRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function register(RegisterTeamRequest $request, RegisterTeamAction $action): JsonResponse
    {
        return ApiResponse::created($action->execute($request->toData()));
    }

    public function activate(Request $request, ActivateAccountAction $action): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);

        return ApiResponse::success($action->execute($data['token']));
    }

    public function resendActivation(Request $request, ResendActivationAction $action): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $action->execute($data['email']);

        return ApiResponse::success(['ok' => true]);
    }

    public function login(Request $request, LoginAction $action): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return ApiResponse::success($action->execute($data['email'], $data['password'], (string) $request->ip()));
    }

    public function logout(LogoutAction $action): Response
    {
        $action->execute();

        return ApiResponse::noContent();
    }

    public function logoutAll(LogoutAllAction $action): Response
    {
        $action->execute(CurrentUser::require());

        return ApiResponse::noContent();
    }

    public function forgotPassword(Request $request, ForgotPasswordAction $action): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $action->execute($data['email']);

        return ApiResponse::success(['ok' => true]);
    }

    public function resetPassword(Request $request, ResetPasswordAction $action): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => array_merge(PasswordRules::rules(), ['same:confirmPassword']),
            'confirmPassword' => ['required'],
        ]);
        $action->execute($data['token'], $data['password']);

        return ApiResponse::success(['ok' => true]);
    }

    public function acceptInvitation(Request $request, AcceptInvitationAction $action): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => array_merge(PasswordRules::rules(), ['same:confirmPassword']),
            'confirmPassword' => ['required'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
        ]);
        $action->execute($data['token'], $data['password'], $data['firstName'] ?? null, $data['lastName'] ?? null);

        return ApiResponse::success(['ok' => true]);
    }
}
