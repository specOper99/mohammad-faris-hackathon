<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = [], int $status = 200, array $meta = []): JsonResponse
    {
        $id = self::correlationId();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(['correlationId' => $id], $meta),
        ], $status)
            ->header('Cache-Control', 'no-store')
            ->header('X-Correlation-ID', $id);
    }

    public static function created(mixed $data = []): JsonResponse
    {
        return self::success($data, 201);
    }

    public static function noContent(): Response
    {
        $id = self::correlationId();

        return response()->noContent()
            ->header('Cache-Control', 'no-store')
            ->header('X-Correlation-ID', $id);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, list<string>>  $errors
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        $id = self::correlationId();

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => (object) $errors,
            'meta' => array_merge(['correlationId' => $id], $meta),
        ], $status)
            ->header('Cache-Control', 'no-store')
            ->header('X-Correlation-ID', $id);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    public static function paged(array $items, int $page, int $pageSize, int $totalCount): JsonResponse
    {
        return self::success([
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalCount' => $totalCount,
        ]);
    }

    private static function correlationId(): string
    {
        $request = request();
        $id = $request->attributes->get('correlation_id')
            ?? $request->headers->get('X-Correlation-ID');

        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid();
            $request->attributes->set('correlation_id', $id);
            $request->headers->set('X-Correlation-ID', $id);
        }

        return $id;
    }
}
