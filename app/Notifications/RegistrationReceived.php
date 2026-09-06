<?php

namespace App\Notifications;

use App\Models\User;

final class RegistrationReceived extends ChallengeNotification
{
    public function __construct(public string $teamCode)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'registration';
    }

    protected function replacements(object $notifiable): array
    {
        return [
            'name' => $notifiable instanceof User ? $notifiable->first_name : '',
            'code' => $this->teamCode,
        ];
    }
}
