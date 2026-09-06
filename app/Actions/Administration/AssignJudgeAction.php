<?php

namespace App\Actions\Administration;

use App\Enums\AuditAction;
use App\Enums\TeamMemberStatus;
use App\Models\JudgeAssignment;
use App\Models\JudgeProfile;
use App\Models\Submission;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\JudgeAssigned;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\OutboxNotifier;

final class AssignJudgeAction
{
    public function __construct(
        private AuditLogger $audit,
        private OutboxNotifier $outbox,
    ) {}

    /**
     * @param  array{judgeProfileId: string, submissionId: string}  $data
     */
    public function execute(User $admin, array $data): JudgeAssignment
    {
        $profile = JudgeProfile::query()->find($data['judgeProfileId']);
        if ($profile === null || ! $profile->is_active) {
            throw AppException::code('JUDGE_INACTIVE', 409);
        }
        $submission = Submission::query()->find($data['submissionId']);
        if ($submission === null) {
            throw AppException::code('SUBMISSION_NOT_FOUND', 404);
        }

        $judgeUser = $profile->user;
        if ($judgeUser === null) {
            throw AppException::code('JUDGE_INACTIVE', 409);
        }

        $conflict = TeamMember::query()
            ->where('team_id', $submission->team_id)
            ->whereIn('status', [TeamMemberStatus::Invited, TeamMemberStatus::Active])
            ->where(function ($q) use ($profile, $judgeUser): void {
                $q->where('user_id', $profile->user_id)
                    ->orWhereHas('user', fn ($u) => $u->whereRaw('lower(email) = ?', [strtolower($judgeUser->email)]));
            })
            ->exists();
        if ($conflict) {
            throw AppException::code('ASSIGNMENT_SELF_TEAM', 409);
        }

        $dup = JudgeAssignment::query()
            ->where('judge_profile_id', $profile->id)
            ->where('submission_id', $submission->id)
            ->whereNull('unassigned_at')
            ->exists();
        if ($dup) {
            throw AppException::code('ASSIGNMENT_DUPLICATE', 409);
        }

        $assignment = JudgeAssignment::query()->create([
            'judge_profile_id' => $profile->id,
            'submission_id' => $submission->id,
            'assigned_at' => now()->toImmutable(),
            'assigned_by_user_id' => $admin->id,
        ]);

        $this->outbox->send($judgeUser, new JudgeAssigned((string) $submission->submission_code));
        $this->audit->write(AuditAction::JUDGE_ASSIGN, $assignment, null, null, $admin);

        return $assignment;
    }
}
