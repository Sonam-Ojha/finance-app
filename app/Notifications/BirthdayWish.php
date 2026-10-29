<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The birthday email (App\Console\Commands\SendBirthdayWishes). */
class BirthdayWish extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = strtok(trim($notifiable->name ?? ''), ' ') ?: null;

        return (new MailMessage)
            ->subject('Happy birthday' . ($first ? ", {$first}" : '') . '! 🎂')
            ->markdown('mail.birthday', ['first' => $first]);
    }
}
