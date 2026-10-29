<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Rishi added you to 🏖️ Goa trip" (App\Support\Groups::addMember). */
class AddedToGroup extends Notification
{
    public function __construct(
        public string $groupLabel,
        public string $addedBy,
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
            ->subject("{$this->addedBy} added you to {$this->groupLabel}")
            ->markdown('mail.group-added', [
                'greeting'   => $name !== '' ? "Hi {$name}," : 'Hi,',
                'groupLabel' => $this->groupLabel,
                'addedBy'    => $this->addedBy,
                'url'        => route('groups.show', $this->groupId),
            ]);
    }
}
