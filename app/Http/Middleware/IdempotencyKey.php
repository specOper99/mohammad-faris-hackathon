<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyRecord;
use App\Support\AppException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $hash = hash('sha256', $request->getContent());
        $userId = $request->user()?->getAuthIdentifier();
        $path = $request->path();

        $existing = IdempotencyRecord::query()
            ->where('key', $key)
            ->where('path', $path)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            if ($existing->body_hash !== $hash) {
                throw AppException::code('IDEMPOTENCY_KEY_REUSED', 409);
            }

            return response()->json($existing->response_json, (int) $existing->status);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            IdempotencyRecord::query()->create([
                'key' => $key,
                'user_id' => $userId,
                'path' => $path,
                'body_hash' => $hash,
                'status' => $response->getStatusCode(),
                'response_json' => $response->getData(true),
            ]);
        }

        return $response;
    }
}
