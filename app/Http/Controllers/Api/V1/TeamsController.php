<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\InviteMemberAction;
use App\Actions\Members\RemoveMemberAction;
use App\Actions\Members\ResendInviteAction;
use App\Actions\Members\UpdateMemberAction;
use App\Actions\Teams\ChangeTrackAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Policies\TeamPolicy;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\MembershipGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TeamsController extends Controller
{
    public function me(Request $request, MembershipGuard $guard): JsonResponse
    {
        $user = CurrentUser::require();
        $team = $guard->currentTeam($user);
        if (! app(TeamPolicy::class)->view($user, $team)) {
            throw (new ModelNotFoundException)->setModel(Team::class);
        }

        return ApiResponse::success((new TeamResource($team))->resolve($request));
    }

    public function update(Request $request, UpdateTeamAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute(CurrentUser::require(), $request->all(), $request));
    }

    public function changeTrack(Request $request, ChangeTrackAction $action): JsonResponse
    {
        $data = $request->validate(['trackId' => ['required', 'uuid']]);
        $action->execute(CurrentUser::require(), $data);

        return ApiResponse::success(['ok' => true]);
    }

    public function members(Request $request, MembershipGuard $guard): JsonResponse
    {
        $team = $guard->currentTeam(CurrentUser::require());

        return ApiResponse::success((new TeamResource($team))->resolve($request)['members']);
    }

    public function invite(Request $request, InviteMemberAction $action): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'skill' => ['nullable', 'string', 'max:200'],
        ]);
        $member = $action->execute(CurrentUser::require(), $data);

        return ApiResponse::created(['id' => $member->id]);
    }

    public function updateMember(Request $request, string $id, UpdateMemberAction $action): JsonResponse
    {
        $data = $request->validate(['skill' => ['nullable', 'string', 'max:200']]);
        $member = $action->execute(CurrentUser::require(), $id, $data);

        return ApiResponse::success(['id' => $member->id, 'skill' => $member->skill]);
    }

    public function removeMember(string $id, RemoveMemberAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $id);

        return ApiResponse::success(['ok' => true]);
    }

    public function resendInvite(string $id, ResendInviteAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $id);

        return ApiResponse::success(['ok' => true]);
    }
}
