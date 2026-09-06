<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Public\PublicReadAction;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicController extends Controller
{
    public function settings(PublicReadAction $action): JsonResponse
    {
        return ApiResponse::success($action->settings());
    }

    public function criteria(PublicReadAction $action): JsonResponse
    {
        return ApiResponse::success($action->criteria());
    }

    public function contact(Request $request, PublicReadAction $action): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $action->contact($data, (string) $request->ip());

        return ApiResponse::success(['ok' => true]);
    }
}
