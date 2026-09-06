<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class ChallengeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    abstract protected function templateKey(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function replacements(object $notifiable): array;

    public function toMail(object $notifiable): MailMessage
    {
        $locale = property_exists($notifiable, 'locale') ? (string) $notifiable->locale : 'en';
        $repl = $this->replacements($notifiable);

        return (new MailMessage)
            ->subject((string) trans('mail.'.$this->templateKey().'_subject', $repl, $locale))
            ->line((string) trans('mail.'.$this->templateKey().'_body', $repl, $locale));
    }

    public function outboxIdempotencyKey(string $email): string
    {
        return $this->templateKey().':'.$email.':'.$this->id;
    }
}
