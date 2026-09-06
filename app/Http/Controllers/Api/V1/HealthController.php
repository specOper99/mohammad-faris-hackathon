<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Storage\ObjectStorage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class HealthController extends Controller
{
    public function ready(ObjectStorage $storage): JsonResponse
    {
        DB::select('select 1');
        if (! $storage->bucketExists()) {
            return ApiResponse::error('INTERNAL_ERROR', 'Object storage unavailable', 503);
        }

        return ApiResponse::success(['ok' => true]);
    }
}
