<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Your October 2026 in numbers" (App\Support\MonthlySummary). */
class MonthlySummaryMail extends Notification
{
    /** @param array<string, mixed> $summary App\Support\MonthlySummary::for() */
    public function __construct(public array $summary) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->name ?? '');

        return (new MailMessage)
            ->subject("Your {$this->summary['label']} in numbers")
            ->markdown('mail.monthly-summary', [
                'greeting' => $name !== '' ? "Hi {$name}," : 'Hi,',
                's'        => $this->summary,
            ]);
    }
}
