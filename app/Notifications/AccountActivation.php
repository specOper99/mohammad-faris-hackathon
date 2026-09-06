<?php

namespace App\Notifications;

use App\Models\User;

final class AccountActivation extends ChallengeNotification
{
    public function __construct(public string $rawToken)
    {
        parent::__construct();
    }

    protected function templateKey(): string
    {
        return 'activation';
    }

    protected function replacements(object $notifiable): array
    {
        $name = $notifiable instanceof User ? $notifiable->first_name : '';

        return [
            'name' => $name,
            'url' => rtrim((string) config('exoplanet.frontend_url'), '/').'/activate?token='.$this->rawToken,
        ];
    }
}
