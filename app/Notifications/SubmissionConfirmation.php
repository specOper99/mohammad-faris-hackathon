<?php

namespace App\Notifications;

use App\Models\User;

final class SubmissionConfirmation extends ChallengeNotification
{
    public function __construct(public string $submissionCode, public string $when)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'submission';
    }

    protected function replacements(object $notifiable): array
    {
        return [
            'name' => $notifiable instanceof User ? $notifiable->first_name : '',
            'code' => $this->submissionCode,
            'when' => $this->when,
        ];
    }
}
