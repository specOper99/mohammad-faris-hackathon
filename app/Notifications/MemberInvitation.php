<?php

namespace App\Notifications;

use App\Models\User;

final class MemberInvitation extends ChallengeNotification
{
    public function __construct(public string $rawToken, public string $teamCode)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'invite';
    }

    protected function replacements(object $notifiable): array
    {
        return [
            'name' => $notifiable instanceof User ? $notifiable->first_name : '',
            'code' => $this->teamCode,
            'url' => rtrim((string) config('exoplanet.frontend_url'), '/').'/invitations/accept?token='.$this->rawToken,
        ];
    }
}
