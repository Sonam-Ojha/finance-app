<?php

namespace App\Notifications;

use App\Support\EmailVerification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "123456 is your Expense Tracker code" — the code is in the subject so it can be read from the notification alone. */
class EmailCodeNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->name ?? '');

        return (new MailMessage)
            ->subject("{$this->code} is your Expense Tracker code")
            ->markdown('mail.email-code', [
                'greeting' => $name !== '' ? "Hi {$name}," : 'Hi,',
                'code'     => $this->code,
                'minutes'  => EmailVerification::TTL_MINUTES,
            ]);
    }
}
