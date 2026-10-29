<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Support answered a help request (App\Support\SupportTickets::reply). Queued. */
class SupportReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $ticketId,
        public string $subject,
        public string $reply,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->name ?? '');

        return (new MailMessage)
            ->subject("Re: {$this->subject}")
            ->markdown('mail.support-reply', [
                'greeting' => $name !== '' ? "Hi {$name}," : 'Hi,',
                'subject'  => $this->subject,
                'reply'    => $this->reply,
                'url'      => route('support.show', $this->ticketId),
            ]);
    }
}
