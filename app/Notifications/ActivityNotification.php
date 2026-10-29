<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "You added an expense", and the like — one email per thing added in the app. Queued (App\Support\ActivityMail). */
class ActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, string> $details label => value, in display order */
    public function __construct(
        public string $subject,
        public string $headline,
        public array $details,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->name ?? '');

        return (new MailMessage)
            ->subject($this->subject)
            ->markdown('mail.activity', [
                'greeting' => $name !== '' ? "Hi {$name}," : 'Hi,',
                'headline' => $this->headline,
                'details'  => $this->details,
            ]);
    }
}
