<?php

namespace App\Notifications;

use App\Models\User;

final class Finalist extends ChallengeNotification
{
    public function __construct(public string $submissionCode)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'finalist';
    }

    protected function replacements(object $notifiable): array
    {
        return [
            'name' => $notifiable instanceof User ? $notifiable->first_name : '',
            'code' => $this->submissionCode,
        ];
    }
}
