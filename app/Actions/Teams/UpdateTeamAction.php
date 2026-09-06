<?php

namespace App\Actions\Teams;

use App\Enums\TeamStatus;
use App\Http\Resources\TeamResource;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AppException;
use App\Support\HttpsUrl;
use App\Support\MembershipGuard;
use Illuminate\Http\Request;

final class UpdateTeamAction
{
    public function __construct(private MembershipGuard $guard) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(User $user, array $data, Request $request): array
    {
        $team = $this->guard->currentTeam($user);
        if (! app(TeamPolicy::class)->update($user, $team)) {
            throw AppException::code('FORBIDDEN', 403);
        }
        if (in_array($team->status, [TeamStatus::Rejected, TeamStatus::Cancelled], true)) {
            throw AppException::code('TEAM_STATUS_INVALID', 409);
        }

        foreach (['githubUrl' => 'github_url', 'portfolioUrl' => 'portfolio_url'] as $in => $col) {
            if (isset($data[$in]) && ! HttpsUrl::isValid($data[$in])) {
                throw AppException::code('VALIDATION_FAILED', 422, [$in => ['HTTPS URL required.']]);
            }
        }

        $team->saveWithVersion([
            'name' => $data['teamName'] ?? $team->name,
            'university' => $data['university'] ?? $team->university,
            'organization' => $data['organization'] ?? $team->organization,
            'city' => $data['city'] ?? $team->city,
            'country' => $data['country'] ?? $team->country,
            'technical_level' => $data['technicalLevel'] ?? $team->technical_level,
            'github_url' => array_key_exists('githubUrl', $data) ? $data['githubUrl'] : $team->github_url,
            'portfolio_url' => array_key_exists('portfolioUrl', $data) ? $data['portfolioUrl'] : $team->portfolio_url,
        ], isset($data['version']) ? (int) $data['version'] : $team->version);

        return (new TeamResource($team->fresh(['members.user', 'track', 'leader'])))->resolve($request);
    }
}
