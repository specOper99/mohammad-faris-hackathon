<?php

namespace App\Actions\Public;

use App\Models\ChallengeSettings;
use App\Models\ContactMessage;
use App\Models\ScoringCriterion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

final class PublicReadAction
{
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $s = ChallengeSettings::current();

        return [
            'challengeYear' => $s->challenge_year,
            'registrationEnabled' => $s->registration_enabled,
            'registrationStart' => $s->registration_start->toIso8601ZuluString(),
            'registrationEnd' => $s->registration_end->toIso8601ZuluString(),
            'submissionEnabled' => $s->submission_enabled,
            'submissionStart' => $s->submission_start->toIso8601ZuluString(),
            'submissionEnd' => $s->submission_end->toIso8601ZuluString(),
            'scoringEnabled' => $s->scoring_enabled,
            'publishResults' => $s->publish_results,
            'maxTeamMembers' => $s->max_team_members,
            'currentRulesVersion' => $s->current_rules_version,
            'currentDataUsageVersion' => $s->current_data_usage_version,
            'requiredFileTypesOnSubmit' => $s->required_file_types_on_submit,
            'requiredFieldsOnSubmit' => $s->required_fields_on_submit,
            'filePolicy' => $s->file_policy,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function criteria(): array
    {
        return ScoringCriterion::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'nameEn' => $c->name_en,
            'nameAr' => $c->name_ar,
            'weight' => (float) $c->weight,
        ])->all();
    }

    /**
     * @param  array{name: string, email: string, subject: string, message: string}  $data
     */
    public function contact(array $data, string $ip): void
    {
        ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip_address' => $ip,
        ]);

        $admin = config('exoplanet.seed_admin_email');
        if (is_string($admin) && $admin !== '') {
            Notification::route('mail', $admin)->notify(new class($data) extends BaseNotification
            {
                /**
                 * @param  array{name: string, email: string, subject: string, message: string}  $payload
                 */
                public function __construct(private array $payload) {}

                /**
                 * @return list<string>
                 */
                public function via(object $notifiable): array
                {
                    return ['mail'];
                }

                public function toMail(object $notifiable): MailMessage
                {
                    return (new MailMessage)
                        ->subject('Contact: '.$this->payload['subject'])
                        ->line($this->payload['name'].' <'.$this->payload['email'].'>')
                        ->line($this->payload['message']);
                }
            });
        }
    }
}
