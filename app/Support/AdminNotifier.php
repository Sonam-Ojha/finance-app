<?php

namespace App\Support;

use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\ScheduledNotification;
use App\Models\User;
use App\Notifications\AdminAnnouncement;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Sends one admin notification — by push, email or both — and keeps it for
 * the app's inbox and the admin's Sent list. Used by Admin › Notifications
 * ("Send now") and by `notifications:send-scheduled`.
 */
class AdminNotifier
{
    /** Users who get emails: they have an address and kept email notifications on. */
    public static function wantsEmail(Builder $users): Builder
    {
        return $users->whereNotNull('email')->where('email', '!=', '')->where('email_notifications', true);
    }

    /**
     * Emails go out after the page has answered when `$afterResponse` (a
     * click in the admin pages), and straight away from the scheduler.
     *
     * @return array{notification: AdminNotification, notes: list<string>, problem: bool}
     */
    public static function send(
        ?User $sender,
        string $title,
        string $body,
        ?User $target,
        bool $viaPush,
        bool $viaEmail,
        bool $afterResponse = true,
        ?ScheduledNotification $schedule = null,
    ): array {
        $tokens = $viaPush
            ? DeviceToken::query()->when($target, fn ($q) => $q->where('user_id', $target->id))->pluck('token')->all()
            : [];
        $emailIds = $viaEmail
            ? self::wantsEmail(User::query())->when($target, fn ($q) => $q->whereKey($target->id))->pluck('id')
            : collect();
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
            'email_recipients'          => $emailIds->count(),
        ]);

        $notes = [];
        $problem = false;

        if ($viaPush) {
            if (! $configured) {
                $problem = true;
                $notes[] = 'No push went out: Firebase is not set up on the server yet. It is saved in the app\'s notification list.';
            } elseif (! $tokens) {
                $notes[] = 'No phone is signed in yet, so nothing was pushed. It is saved in the app\'s notification list.';
            } else {
                $result = Fcm::send($tokens, $title, $body, ['type' => 'admin', 'id' => (string) $notification->id]);
                $notification->update(['delivered' => $result['delivered'], 'failed' => $result['failed']]);
                // Tokens from phones that removed the app: no use keeping them.
                if ($result['invalid']) DeviceToken::whereIn('token', $result['invalid'])->delete();
                $problem = $result['failed'] > 0;
                $notes[] = "Push delivered to {$result['delivered']} of " . count($tokens) . ' device(s).';
            }
        }

        if ($viaEmail) {
            if ($emailIds->isEmpty()) {
                $notes[] = $target ? "{$target->email} turned emails off, so no email went out." : 'No user has emails turned on, so no email went out.';
            } else {
                self::sendEmails($notification, $emailIds->all(), $afterResponse);
                $notes[] = 'Emailing ' . $emailIds->count() . ' ' . ($emailIds->count() === 1 ? 'user' : 'users') . '.';
            }
        }

        return ['notification' => $notification, 'notes' => $notes, 'problem' => $problem];
    }

    /**
     * One email each. From a web request they go after the page has
     * answered, so a slow mail server never holds up the admin — and no
     * queue worker is needed on the server.
     *
     * @param list<int> $ids
     */
    private static function sendEmails(AdminNotification $notification, array $ids, bool $afterResponse): void
    {
        $announcement = new AdminAnnouncement($notification->title, $notification->body);
        $send = function () use ($notification, $ids, $announcement) {
            ignore_user_abort(true);
            @set_time_limit(0);
            $sent = $failed = 0;
            foreach (User::whereIn('id', $ids)->lazyById(100) as $user) {
                try {
                    $user->notify($announcement);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
            $notification->update(['emails_sent' => $sent, 'emails_failed' => $failed]);
        };

        $afterResponse ? defer($send) : $send();
    }
}
