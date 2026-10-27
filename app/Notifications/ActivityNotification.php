<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "You added an expense", and the like — one email per thing added in the app. */
class ActivityNotification extends Notification
{
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
