<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = [], int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(self::baseMeta(), $meta),
        ], $status)->header('Cache-Control', 'no-store');
    }

    public static function created(mixed $data = []): JsonResponse
    {
        return self::success($data, 201);
    }

    public static function noContent(): Response
    {
        return response()->noContent()->header('Cache-Control', 'no-store');
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
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => (object) $errors,
            'meta' => array_merge(self::baseMeta(), $meta),
        ], $status)->header('Cache-Control', 'no-store');
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

    /**
     * @return array<string, mixed>
     */
    private static function baseMeta(): array
    {
        return [
            'correlationId' => request()->attributes->get('correlation_id')
                ?? request()->headers->get('X-Correlation-ID'),
        ];
    }
}
