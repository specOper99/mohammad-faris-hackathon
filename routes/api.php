<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\JudgeController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\SubmissionsController;
use App\Http\Controllers\Api\V1\TeamsController;
use App\Http\Controllers\Api\V1\TracksController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\IdempotencyKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health/ready', [HealthController::class, 'ready']);

    Route::middleware(['throttle:public-get'])->group(function (): void {
        Route::get('public/settings', [PublicController::class, 'settings']);
        Route::get('public/criteria', [PublicController::class, 'criteria']);
        Route::get('tracks', [TracksController::class, 'index']);
        Route::get('tracks/{id}', [TracksController::class, 'show']);
    });
    Route::post('public/contact', [PublicController::class, 'contact'])->middleware('throttle:public-contact');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware(['throttle:auth-register', IdempotencyKey::class]);
        Route::post('activate', [AuthController::class, 'activate'])->middleware('throttle:auth-activate');
        Route::post('resend-activation', [AuthController::class, 'resendActivation'])->middleware('throttle:auth-forgot');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-forgot');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-forgot');
        Route::post('invitations/accept', [AuthController::class, 'acceptInvitation']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->middleware(['auth:sanctum', EnsureAccountActive::class]);
    });

    Route::middleware(['auth:sanctum', EnsureAccountActive::class, 'throttle:api'])->group(function (): void {
        Route::get('users/me', [UsersController::class, 'me']);
        Route::put('users/me', [UsersController::class, 'updateMe']);
        Route::put('users/me/password', [UsersController::class, 'updatePassword']);

        Route::get('teams/me', [TeamsController::class, 'me']);
        Route::put('teams/me', [TeamsController::class, 'update']);
        Route::put('teams/me/track', [TeamsController::class, 'changeTrack']);
        Route::get('teams/me/members', [TeamsController::class, 'members']);
        Route::post('teams/me/members', [TeamsController::class, 'invite'])->middleware('throttle:invite');
        Route::put('teams/me/members/{id}', [TeamsController::class, 'updateMember']);
        Route::delete('teams/me/members/{id}', [TeamsController::class, 'removeMember']);
        Route::post('teams/me/members/{id}/resend-invite', [TeamsController::class, 'resendInvite'])->middleware('throttle:invite');

        Route::post('submissions', [SubmissionsController::class, 'store']);
        Route::get('submissions/me', [SubmissionsController::class, 'me']);
        Route::get('submissions/{id}', [SubmissionsController::class, 'show']);
        Route::put('submissions/{id}', [SubmissionsController::class, 'update']);
        Route::post('submissions/{id}/submit', [SubmissionsController::class, 'submit'])->middleware(IdempotencyKey::class);
        Route::post('submissions/{id}/files', [SubmissionsController::class, 'directUpload']);
        Route::post('submissions/{id}/files/uploads', [SubmissionsController::class, 'initiateUpload'])->middleware('throttle:upload-initiate');
        Route::post('submissions/{id}/files/uploads/{sessionId}/complete', [SubmissionsController::class, 'completeUpload']);
        Route::post('submissions/{id}/files/uploads/{sessionId}/abort', [SubmissionsController::class, 'abortUpload']);
        Route::delete('submissions/{id}/files/{fileId}', [SubmissionsController::class, 'destroyFile']);
        Route::get('submissions/{id}/files/{fileId}/download', [SubmissionsController::class, 'download']);

        Route::middleware('role:judge')->prefix('judge')->group(function (): void {
            Route::get('submissions', [JudgeController::class, 'submissions']);
            Route::get('submissions/{id}', [JudgeController::class, 'show']);
            Route::get('submissions/{id}/evaluation', [JudgeController::class, 'evaluation']);
            Route::put('submissions/{id}/evaluation', [JudgeController::class, 'updateEvaluation']);
            Route::post('submissions/{id}/evaluation/submit', [JudgeController::class, 'submitEvaluation']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function (): void {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('teams', [AdminController::class, 'teams']);
            Route::get('teams/{id}', [AdminController::class, 'team']);
            Route::put('teams/{id}', [AdminController::class, 'updateTeam']);
            Route::put('teams/{id}/status', [AdminController::class, 'teamStatus']);
            Route::post('teams/{id}/transfer-leadership', [AdminController::class, 'transferLeadership']);
            Route::post('teams/{id}/members/invite', [AdminController::class, 'inviteMember']);
            Route::get('users', [AdminController::class, 'users']);
            Route::put('users/{id}/active', [AdminController::class, 'setActive']);
            Route::post('users/{id}/force-logout', [AdminController::class, 'forceLogout']);
            Route::post('judges', [AdminController::class, 'createJudge']);
            Route::get('judges/{id}', [AdminController::class, 'showJudge']);
            Route::put('judges/{id}', [AdminController::class, 'updateJudge']);
            Route::post('assignments', [AdminController::class, 'assign']);
            Route::delete('assignments/{id}', [AdminController::class, 'unassign']);
            Route::get('submissions', [AdminController::class, 'submissions']);
            Route::get('submissions/{id}', [AdminController::class, 'showSubmission']);
            Route::put('submissions/{id}/status', [AdminController::class, 'submissionStatus']);
            Route::post('submissions/{id}/reopen', [AdminController::class, 'reopenSubmission']);
            Route::put('submissions/{id}/publication', [AdminController::class, 'publication']);
            Route::get('evaluations', [AdminController::class, 'evaluations']);
            Route::post('evaluations/{id}/reopen', [AdminController::class, 'reopenEvaluation']);
            Route::get('evaluations/aggregation/{submissionId}', [AdminController::class, 'aggregation']);
            Route::post('tracks', [AdminController::class, 'storeTrack']);
            Route::put('tracks/{id}', [AdminController::class, 'updateTrack']);
            Route::get('criteria', [AdminController::class, 'criteria']);
            Route::put('criteria', [AdminController::class, 'replaceCriteria']);
            Route::get('settings', [AdminController::class, 'settings']);
            Route::put('settings', [AdminController::class, 'updateSettings']);
            Route::get('export/teams.csv', [AdminController::class, 'exportTeams'])->middleware('throttle:export');
            Route::get('export/teams.xlsx', [AdminController::class, 'exportTeams'])->middleware('throttle:export');
            Route::get('export/submissions.csv', [AdminController::class, 'exportSubmissions'])->middleware('throttle:export');
            Route::get('export/submissions.xlsx', [AdminController::class, 'exportSubmissions'])->middleware('throttle:export');
            Route::get('export/evaluations.csv', [AdminController::class, 'exportEvaluations'])->middleware('throttle:export');
            Route::get('export/evaluations.xlsx', [AdminController::class, 'exportEvaluations'])->middleware('throttle:export');
            Route::get('audit-logs', [AdminController::class, 'auditLogs']);
        });
    });
});
