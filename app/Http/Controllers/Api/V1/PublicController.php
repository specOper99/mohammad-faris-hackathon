<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Public\PublicReadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Public', weight: 2)]
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

    public function contact(ContactRequest $request, PublicReadAction $action): JsonResponse
    {
        $action->contact($request->validated(), (string) $request->ip());

        return ApiResponse::success(['ok' => true]);
    }
}
