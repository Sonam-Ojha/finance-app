<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ActivityNotification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Emails the user about something they just added. Sent after the response
 * has gone out, so a slow or failing mail server never delays or breaks the
 * save itself — and it needs no queue worker running on the server.
 *
 * Called from the controllers rather than from model events on purpose: the
 * one-time import creates hundreds of rows through the same models.
 */
class ActivityMail
{
    /** @param array<string, string|null> $details label => value; nulls are left out */
    public static function send(User $user, string $subject, string $headline, array $details): void
    {
        // Off only when the user turned it off: a model that has not been
        // read back from the database has no value yet, and the default is on.
        if (! config('mail.activity_notifications') || $user->email_notifications === false || ! $user->email) {
            return;
        }

        $notification = new ActivityNotification(
            $subject,
            $headline,
            array_filter($details, fn ($value) => $value !== null && $value !== ''),
        );

        defer(function () use ($user, $notification) {
            try {
                $user->notify($notification);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
