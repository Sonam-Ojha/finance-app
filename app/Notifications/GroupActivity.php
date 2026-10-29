<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email a group member gets instead of a push when no phone of theirs
 * is signed in (App\Support\Groups::tell): "🏖️ Goa trip — Rishi added
 * 'Dinner' ₹4,000.00", with lines like "Your share ₹2,000.00" and a button
 * to open the group. Queued.
 */
class GroupActivity extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $lines shown under the headline, one per line */
    public function __construct(
        public string $subject,
        public string $headline,
        public array $lines,
        public int $groupId,
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
            ->markdown('mail.group-activity', [
                'greeting' => $name !== '' ? "Hi {$name}," : 'Hi,',
                'headline' => $this->headline,
                'lines'    => $this->lines,
                'url'      => route('groups.show', $this->groupId),
            ]);
    }
}
