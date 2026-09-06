<?php

namespace App\Notifications;

use App\Models\User;

final class PasswordReset extends ChallengeNotification
{
    public function __construct(public string $rawToken)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'reset';
    }

    protected function replacements(object $notifiable): array
    {
        return [
            'name' => $notifiable instanceof User ? $notifiable->first_name : '',
            'url' => rtrim((string) config('exoplanet.frontend_url'), '/').'/reset-password?token='.$this->rawToken,
        ];
    }
}
