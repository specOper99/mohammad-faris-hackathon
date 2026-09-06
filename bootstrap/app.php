<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\RequestLog;
use App\Http\Middleware\SetLocale;
use App\Support\ApiResponse;
use App\Support\AppException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->api(prepend: [
            CorrelationId::class,
            SetLocale::class,
            RequestLog::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AppException $e, Request $request) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->errors);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::error('VALIDATION_FAILED', trans('messages.VALIDATION_FAILED'), 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ApiResponse::error('UNAUTHORIZED', trans('messages.UNAUTHORIZED'), 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return ApiResponse::error('FORBIDDEN', trans('messages.FORBIDDEN'), 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return ApiResponse::error('NOT_FOUND', trans('messages.NOT_FOUND'), 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return ApiResponse::error('NOT_FOUND', trans('messages.NOT_FOUND'), 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            $retry = $e->getHeaders()['Retry-After'] ?? 60;

            return ApiResponse::error('RATE_LIMITED', trans('messages.RATE_LIMITED'), 429)
                ->header('Retry-After', (string) $retry);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 403) {
                return ApiResponse::error('FORBIDDEN', trans('messages.FORBIDDEN'), 403);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof AppException) {
                return null;
            }
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            report($e);

            return ApiResponse::error('INTERNAL_ERROR', trans('messages.INTERNAL_ERROR'), 500);
        });
    })->create();
