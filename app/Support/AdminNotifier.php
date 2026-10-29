<?php

namespace App\Support;

use App\Jobs\SendAdminNotification;
use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\ScheduledNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Sends one admin notification — by push, email or both — and keeps it for
 * the app's inbox and the admin's Sent list. Used by Admin › Notifications
 * ("Send now") and by `notifications:send-scheduled`.
 *
 * The row is saved at once (so it is in every inbox straight away); the
 * pushes and emails go out from the queue in small batches
 * (App\Jobs\SendAdminNotification), each adding to the row's counts. Until
 * they are all in, the Sent list says "sending…".
 */
class AdminNotifier
{
    /** Users who get emails: they have an address and kept email notifications on. */
    public static function wantsEmail(Builder $users): Builder
    {
        return $users->whereNotNull('email')->where('email', '!=', '')->where('email_notifications', true);
    }

    /**
     * @return array{notification: AdminNotification, notes: list<string>, problem: bool}
     */
    public static function send(
        ?User $sender,
        string $title,
        string $body,
        ?User $target,
        bool $viaPush,
        bool $viaEmail,
        ?ScheduledNotification $schedule = null,
    ): array {
        $tokens = $viaPush
            ? DeviceToken::query()->when($target, fn ($q) => $q->where('user_id', $target->id))->pluck('token')->all()
            : [];
        $emailIds = $viaEmail
            ? self::wantsEmail(User::query())->when($target, fn ($q) => $q->whereKey($target->id))->pluck('id')->all()
            : [];
        $configured = Fcm::configured();

        $notification = AdminNotification::create([
            'sent_by'                   => $sender?->id,
            'title'                     => $title,
            'body'                      => $body,
            'audience'                  => $target ? AdminNotification::AUDIENCE_USER : AdminNotification::AUDIENCE_ALL,
            'target_user_id'            => $target?->id,
            'scheduled_notification_id' => $schedule?->id,
            'via_push'                  => $viaPush,
            'via_email'                 => $viaEmail,
            'status'                    => $configured || ! $viaPush ? AdminNotification::STATUS_SENT : AdminNotification::STATUS_NOT_CONFIGURED,
            'devices'                   => count($tokens),
            'email_recipients'          => count($emailIds),
        ]);

        $notes = [];
        $problem = false;
        $sending = [];

        if ($viaPush) {
            if (! $configured) {
                $problem = true;
                $notes[] = 'No push went out: Firebase is not set up on the server yet. It is saved in the app\'s notification list.';
            } elseif (! $tokens) {
                $notes[] = 'No phone is signed in yet, so nothing was pushed. It is saved in the app\'s notification list.';
            } else {
                $sending[] = count($tokens) . ' ' . Str::plural('phone', count($tokens));
            }
        }

        if ($viaEmail && ! $emailIds) {
            $notes[] = $target ? "{$target->email} turned emails off, so no email went out." : 'No user has emails turned on, so no email went out.';
        } elseif ($emailIds) {
            $sending[] = count($emailIds) . ' ' . Str::plural('email', count($emailIds));
        }

        if ($sending) {
            $notes[] = 'Sending to ' . implode(' and ', $sending) . ' — the counts appear in Sent shortly.';
        }

        // Queued once the message above is worded: with the sync queue (tests,
        // local) the batches run right here.
        if ($viaPush && $configured) {
            foreach (array_chunk($tokens, SendAdminNotification::PUSH_BATCH) as $batch) {
                SendAdminNotification::dispatch($notification->id, $batch, []);
            }
        }
        foreach (array_chunk($emailIds, SendAdminNotification::EMAIL_BATCH) as $batch) {
            SendAdminNotification::dispatch($notification->id, [], $batch);
        }

        return ['notification' => $notification, 'notes' => $notes, 'problem' => $problem];
    }
}
