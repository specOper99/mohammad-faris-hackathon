<?php

namespace App\Support;

use App\Enums\EmailOutboxStatus;
use App\Models\EmailOutbox;
use App\Models\User;
use App\Notifications\ChallengeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class OutboxNotifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(User|string $to, ChallengeNotification $notification, array $payload = []): void
    {
        $email = $to instanceof User ? $to->email : $to;
        EmailOutbox::query()->create([
            'template_key' => class_basename($notification),
            'to_email' => $email,
            'payload' => $payload,
            'status' => EmailOutboxStatus::Pending,
            'attempt_count' => 0,
            'idempotency_key' => class_basename($notification).':'.$email.':'.(string) Str::uuid(),
        ]);

        if ($to instanceof User) {
            $to->notify($notification);
        } else {
            Notification::route('mail', $to)->notify($notification);
        }
    }
}
