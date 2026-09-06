<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\InviteMemberAction;
use App\Actions\Members\RemoveMemberAction;
use App\Actions\Members\ResendInviteAction;
use App\Actions\Members\UpdateMemberAction;
use App\Actions\Teams\ChangeTrackAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\InviteMemberRequest;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Requests\Teams\ChangeTrackRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Policies\TeamPolicy;
use App\Support\ApiResponse;
use App\Support\CurrentUser;
use App\Support\MembershipGuard;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Teams', weight: 5)]
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

    public function update(UpdateTeamRequest $request, UpdateTeamAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute(CurrentUser::require(), $request->validated(), $request));
    }

    public function changeTrack(ChangeTrackRequest $request, ChangeTrackAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $request->validated());

        return ApiResponse::success(['ok' => true]);
    }

    public function members(Request $request, MembershipGuard $guard): JsonResponse
    {
        $team = $guard->currentTeam(CurrentUser::require());

        return ApiResponse::success((new TeamResource($team))->resolve($request)['members']);
    }

    public function invite(InviteMemberRequest $request, InviteMemberAction $action): JsonResponse
    {
        $member = $action->execute(CurrentUser::require(), $request->validated());

        return ApiResponse::created(['id' => $member->id]);
    }

    public function updateMember(UpdateMemberRequest $request, string $id, UpdateMemberAction $action): JsonResponse
    {
        $member = $action->execute(CurrentUser::require(), $id, $request->validated());

        return ApiResponse::success(['id' => $member->id, 'skill' => $member->skill]);
    }

    public function removeMember(string $id, RemoveMemberAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $id);

        return ApiResponse::success(['ok' => true]);
    }

    #[Endpoint(description: 'Resend the pending invitation email. No request body.')]
    public function resendInvite(string $id, ResendInviteAction $action): JsonResponse
    {
        $action->execute(CurrentUser::require(), $id);

        return ApiResponse::success(['ok' => true]);
    }
}
