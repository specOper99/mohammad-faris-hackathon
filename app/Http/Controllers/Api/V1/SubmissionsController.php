<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Files\AbortUploadAction;
use App\Actions\Files\CompleteUploadAction;
use App\Actions\Files\DeleteFileAction;
use App\Actions\Files\DownloadFileAction;
use App\Actions\Files\InitiateUploadAction;
use App\Actions\Submissions\GetOrCreateDraftAction;
use App\Actions\Submissions\SubmitSubmissionAction;
use App\Actions\Submissions\UpdateSubmissionAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionResource;
use App\Models\ChallengeSettings;
use App\Models\Submission;
use App\Policies\SubmissionPolicy;
use App\Support\ApiResponse;
use App\Support\AppException;
use App\Support\CurrentUser;
use App\Support\MembershipGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubmissionsController extends Controller
{
    public function store(GetOrCreateDraftAction $action): JsonResponse
    {
        $sub = $action->execute(CurrentUser::require());

        return ApiResponse::success((new SubmissionResource($sub))->resolve());
    }

    public function me(MembershipGuard $guard): JsonResponse
    {
        $team = $guard->currentTeam(CurrentUser::require());
        $sub = Submission::query()->where('team_id', $team->id)->first();
        if ($sub === null) {
            throw AppException::code('SUBMISSION_NOT_FOUND', 404);
        }

        return $this->show($sub->id);
    }

    public function show(string $id): JsonResponse
    {
        $sub = Submission::query()->find($id);
        $user = CurrentUser::require();
        if ($sub === null || ! app(SubmissionPolicy::class)->view($user, $sub)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        return ApiResponse::success((new SubmissionResource($sub))->resolve(request()));
    }

    public function update(Request $request, string $id, UpdateSubmissionAction $action): JsonResponse
    {
        $sub = Submission::query()->find($id);
        if ($sub === null) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }
        $updated = $action->execute(CurrentUser::require(), $sub, $request->all());

        return ApiResponse::success((new SubmissionResource($updated))->resolve($request));
    }

    public function submit(string $id, SubmitSubmissionAction $action): JsonResponse
    {
        $sub = Submission::query()->find($id);
        if ($sub === null) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }
        $updated = $action->execute(CurrentUser::require(), $sub);

        return ApiResponse::success((new SubmissionResource($updated))->resolve(request()));
    }

    public function initiateUpload(Request $request, string $id, InitiateUploadAction $action): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $data = $request->validate([
            'fileType' => ['required', 'string'],
            'fileName' => ['required', 'string', 'max:200'],
            'mimeType' => ['required', 'string'],
            'sizeBytes' => ['required', 'integer', 'min:1'],
            'partSizeBytes' => ['nullable', 'integer', 'min:1'],
        ]);

        return ApiResponse::success($action->execute(CurrentUser::require(), $sub, $data));
    }

    public function completeUpload(Request $request, string $id, string $sessionId, CompleteUploadAction $action): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $file = $action->execute(CurrentUser::require(), $sub, $sessionId, $request->all());

        return ApiResponse::created([
            'id' => $file->id,
            'fileType' => $file->file_type->value,
            'originalFileName' => $file->original_file_name,
            'fileSize' => $file->file_size,
            'scanStatus' => $file->scan_status->value,
        ]);
    }

    public function abortUpload(string $id, string $sessionId, AbortUploadAction $action): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $action->execute(CurrentUser::require(), $sub, $sessionId);

        return ApiResponse::success(['ok' => true]);
    }

    public function destroyFile(string $id, string $fileId, DeleteFileAction $action): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);
        $action->execute(CurrentUser::require(), $sub, $fileId);

        return ApiResponse::success(['ok' => true]);
    }

    public function download(string $id, string $fileId, DownloadFileAction $action): JsonResponse
    {
        $sub = Submission::query()->findOrFail($id);

        return ApiResponse::success($action->execute(CurrentUser::require(), $sub, $fileId));
    }

    public function directUpload(string $id): JsonResponse
    {
        $cap = ChallengeSettings::current()->allow_direct_upload_below_bytes;
        if ($cap <= 0) {
            throw AppException::code('USE_PRESIGNED_UPLOAD', 413);
        }
        throw AppException::code('USE_PRESIGNED_UPLOAD', 413);
    }
}
