<?php

namespace App\Actions\Administration;

use App\Enums\AuditAction;
use App\Enums\OneTimeTokenPurpose;
use App\Models\JudgeProfile;
use App\Models\User;
use App\Notifications\AccountActivation;
use App\Support\AuditLogger;
use App\Support\Clock;
use App\Support\OneTimeTokenService;
use App\Support\OutboxNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateJudgeAction
{
    public function __construct(
        private AuditLogger $audit,
        private OutboxNotifier $outbox,
        private OneTimeTokenService $tokens,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $admin, array $data): JudgeProfile
    {
        return DB::transaction(function () use ($admin, $data) {
            $password = $data['password'] ?? Str::password(16);
            $user = User::query()->whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
            if ($user === null) {
                $user = User::query()->create([
                    'first_name' => $data['firstName'],
                    'last_name' => $data['lastName'],
                    'email' => strtolower($data['email']),
                    'password' => $password,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            }
            $user->syncRoles(['judge']);

            $profile = JudgeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => $data['specialization'] ?? null,
                    'is_active' => true,
                    'created_by_user_id' => $admin->id,
                ],
            );

            if (! empty($data['sendInviteEmail'])) {
                $issued = $this->tokens->issue(
                    OneTimeTokenPurpose::PasswordReset,
                    $user,
                    $this->clock->now()->addHours(24),
                );
                $this->outbox->send($user, new AccountActivation($issued['token']));
            }

            $this->audit->write(AuditAction::JUDGE_CREATE, $profile, null, null, $admin);

            return $profile->load('user');
        });
    }
}
