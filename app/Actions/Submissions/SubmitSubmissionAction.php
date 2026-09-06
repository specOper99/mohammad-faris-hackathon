<?php

namespace App\Actions\Submissions;

use App\Domain\Codes\PublicCodeFormatter;
use App\Domain\Submissions\SubmissionWindow;
use App\Enums\AuditAction;
use App\Enums\FileScanStatus;
use App\Enums\LockReason;
use App\Enums\SubmissionStatus;
use App\Enums\TeamMemberStatus;
use App\Enums\TeamStatus;
use App\Models\ChallengeSettings;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionConfirmation;
use App\Policies\SubmissionPolicy;
use App\Support\AppException;
use App\Support\AuditLogger;
use App\Support\Clock;
use App\Support\CodeSequence;
use App\Support\HttpsUrl;
use App\Support\OutboxNotifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class SubmitSubmissionAction
{
    public function __construct(
        private Clock $clock,
        private SubmissionWindow $window,
        private CodeSequence $sequences,
        private PublicCodeFormatter $codes,
        private OutboxNotifier $outbox,
        private AuditLogger $audit,
    ) {}

    public function execute(User $user, Submission $submission): Submission
    {
        if (! app(SubmissionPolicy::class)->submit($user, $submission)) {
            throw (new ModelNotFoundException)->setModel(Submission::class);
        }

        $settings = ChallengeSettings::current();
        $now = $this->clock->now();

        return DB::transaction(function () use ($user, $submission, $settings, $now) {
            /** @var Submission $locked */
            $locked = Submission::query()->where('id', $submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SubmissionStatus::Draft) {
                throw AppException::code('SUBMISSION_ALREADY_SUBMITTED', 409);
            }

            if (! $this->window->isOpen($now, $settings->submission_enabled, $settings->submission_start, $settings->submission_end)) {
                if ($this->window->deadlinePassed($now, $settings->submission_end)) {
                    if ($locked->locked_at === null && $settings->lock_on_deadline) {
                        $locked->locked_at = $now;
                        $locked->lock_reason = LockReason::Deadline;
                        $locked->save();
                    }
                    throw AppException::code('SUBMISSION_DEADLINE_PASSED', 409);
                }
                throw AppException::code('SUBMISSION_WINDOW_CLOSED', 409);
            }

            $team = $locked->team()->lockForUpdate()->firstOrFail();
            $allowedStatuses = $settings->require_admin_team_confirmation
                ? [TeamStatus::Confirmed]
                : [TeamStatus::Confirmed, TeamStatus::Registered];
            if (! in_array($team->status, $allowedStatuses, true)) {
                throw AppException::code('TEAM_STATUS_INVALID', 409);
            }
            if ($team->track_id !== $locked->track_id) {
                throw AppException::code('TRACK_FROZEN', 409);
            }

            $this->assertFields($locked, $settings);
            $this->assertFiles($locked, $settings, $team->track->code);

            $year = (int) $settings->challenge_year;
            $code = $locked->submission_code ?: $this->codes->submission($year, $this->sequences->next('submission_code_seq'));

            $locked->submission_code = $code;
            $locked->status = SubmissionStatus::Submitted;
            $locked->submitted_at = $now;
            $locked->first_submitted_at = $locked->first_submitted_at ?? $now;
            $locked->updated_by_user_id = $user->id;
            if ($settings->lock_on_submit) {
                $locked->locked_at = $now;
                $locked->lock_reason = LockReason::Submitted;
            }
            $locked->save();

            $when = $now->toIso8601ZuluString();
            $this->outbox->send($team->leader, new SubmissionConfirmation($code, $when));
            if ($settings->email_all_members_on_submit) {
                foreach ($team->members()->where('status', TeamMemberStatus::Active)->with('user')->get() as $member) {
                    if ($member->user_id !== $team->leader_user_id && $member->user) {
                        $this->outbox->send($member->user, new SubmissionConfirmation($code, $when));
                    }
                }
            }
            $this->audit->write(AuditAction::SUBMISSION_SUBMIT, $locked, ['status' => 'draft'], ['status' => 'submitted', 'code' => $code], $user);

            return $locked->fresh(['currentFiles', 'track', 'team']);
        });
    }

    private function assertFields(Submission $submission, ChallengeSettings $settings): void
    {
        $required = $settings->required_fields_on_submit ?? [];
        $map = [
            'projectName' => [$submission->project_name, 300],
            'abstract' => [$submission->abstract, 5000],
            'problemDescription' => [$submission->problem_description, 20000],
            'solutionDescription' => [$submission->solution_description, 20000],
            'limitations' => [$submission->limitations, 10000],
            'aiUsage' => [$submission->ai_usage, 10000],
            'githubUrl' => [$submission->github_url, 500],
        ];
        $errors = [];
        foreach ($required as $field) {
            [$value, $max] = $map[$field] ?? [null, 0];
            $trim = is_string($value) ? trim($value) : '';
            if ($trim === '' || ($max && strlen($trim) > $max)) {
                $errors[$field] = ['Required.'];
            }
        }
        if ($errors !== []) {
            throw AppException::code('SUBMISSION_REQUIRED_FIELDS', 422, $errors);
        }
        $forbidLocal = app()->environment('production');
        if (! HttpsUrl::isValid($submission->github_url, $forbidLocal)) {
            throw AppException::code('SUBMISSION_GITHUB_INVALID', 422);
        }
    }

    private function assertFiles(Submission $submission, ChallengeSettings $settings, string $trackCode): void
    {
        $required = $settings->required_file_types_on_submit ?? [];
        $byTrack = $settings->required_file_types_by_track_code[$trackCode] ?? [];
        $required = array_values(array_unique(array_merge($required, $byTrack)));
        $current = $submission->currentFiles()->get()->keyBy(fn ($f) => $f->file_type->value);
        $missing = [];
        foreach ($required as $type) {
            $file = $current->get($type);
            if ($file === null) {
                $missing[$type] = ['Required.'];
            } elseif ($file->scan_status === FileScanStatus::Infected) {
                throw AppException::code('FILE_INFECTED', 422);
            }
        }
        if ($missing !== []) {
            throw AppException::code('SUBMISSION_REQUIRED_FILES', 422, $missing);
        }
    }
}
