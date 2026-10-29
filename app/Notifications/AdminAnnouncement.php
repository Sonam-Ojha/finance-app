<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The email copy of a notification an admin sent from Admin › Notifications. */
class AdminAnnouncement extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->name ?? '');

        return (new MailMessage)
            ->subject($this->title)
            ->markdown('mail.announcement', [
                'greeting' => $name !== '' ? "Hi {$name}," : 'Hi,',
                'title'    => $this->title,
                'body'     => $this->body,
            ]);
    }
}
