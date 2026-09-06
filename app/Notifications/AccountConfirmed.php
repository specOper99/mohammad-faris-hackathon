<?php

namespace App\Notifications;

use App\Models\User;

final class AccountConfirmed extends ChallengeNotification
{
    public function __construct(public string $teamCode)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'confirmed';
    }

    protected function replacements(object $notifiable): array
    {
        return [
            'name' => $notifiable instanceof User ? $notifiable->first_name : '',
            'code' => $this->teamCode,
        ];
    }
}
