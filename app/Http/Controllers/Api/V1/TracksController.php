<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackResource;
use App\Models\Track;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TracksController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Track::query()->orderBy('sort_order');
        if ($request->boolean('includeInactive') !== true) {
            $q->where('is_active', true);
        }

        return ApiResponse::success(TrackResource::collection($q->get())->resolve());
    }

    public function show(string $id): JsonResponse
    {
        $track = Track::query()->findOrFail($id);

        return ApiResponse::success((new TrackResource($track))->resolve());
    }
}
