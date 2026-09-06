<?php

namespace App\Listeners;

use App\Enums\EmailOutboxStatus;
use App\Models\EmailOutbox;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

final class UpdateEmailOutbox
{
    public function handleSent(NotificationSent $event): void
    {
        $email = $this->email($event->notifiable);
        if ($email === null) {
            return;
        }

        EmailOutbox::query()
            ->where('to_email', $email)
            ->where('template_key', class_basename($event->notification))
            ->where('status', EmailOutboxStatus::Pending)
            ->orderByDesc('created_at')
            ->limit(1)
            ->update([
                'status' => EmailOutboxStatus::Sent,
                'sent_at' => now(),
            ]);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $email = $this->email($event->notifiable);
        if ($email === null) {
            return;
        }

        $row = EmailOutbox::query()
            ->where('to_email', $email)
            ->where('template_key', class_basename($event->notification))
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return;
        }

        $attempts = $row->attempt_count + 1;
        $row->update([
            'attempt_count' => $attempts,
            'last_error' => 'notification_failed',
            'status' => $attempts >= 3 ? EmailOutboxStatus::Poison : EmailOutboxStatus::Failed,
        ]);
    }

    private function email(mixed $notifiable): ?string
    {
        if (is_object($notifiable) && isset($notifiable->email)) {
            return (string) $notifiable->email;
        }
        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationForMail')) {
            $route = $notifiable->routeNotificationForMail();

            return is_string($route) ? $route : null;
        }

        return null;
    }
}
