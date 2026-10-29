<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AdminAnnouncement;
use App\Support\Fcm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One batch of an admin notification (App\Support\AdminNotifier): pushes to
 * some phones, or emails some users. Each batch adds what happened to the
 * row's counts (delivered / failed, emails_sent / emails_failed), so the
 * admin's Sent list shows "sending…" until every batch is in.
 *
 * Batches are small so each one finishes well inside the worker's limits,
 * and are tried once: a retry would push or email the same people twice.
 * A batch that fails anyway counts its people as failed.
 */
class SendAdminNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const PUSH_BATCH  = 100; // one round of parallel requests to Firebase
    public const EMAIL_BATCH = 20;

    public int $tries = 1;

    public int $timeout = 75;

    /**
     * One or the other per batch (see failed()).
     *
     * @param list<string> $tokens  phones to push to
     * @param list<int>    $userIds users to email
     */
    public function __construct(
        public int $notificationId,
        public array $tokens = [],
        public array $userIds = [],
    ) {}

    public function handle(): void
    {
        $notification = AdminNotification::find($this->notificationId);
        if (! $notification) return;

        if ($this->tokens) {
            $result = Fcm::send($this->tokens, $notification->title, $notification->body, ['type' => 'admin', 'id' => (string) $notification->id]);
            // Tokens from phones that removed the app: no use keeping them.
            if ($result['invalid']) DeviceToken::whereIn('token', $result['invalid'])->delete();
            // Firebase gone from the server since: those phones were not reached.
            $missed = count($this->tokens) - $result['delivered'] - $result['failed'];
            AdminNotification::whereKey($notification->id)->incrementEach(['delivered' => $result['delivered'], 'failed' => $result['failed'] + $missed]);
        }

        if ($this->userIds) {
            $announcement = new AdminAnnouncement($notification->title, $notification->body);
            $sent = $failed = 0;
            $users = User::whereIn('id', $this->userIds)->get();
            foreach ($users as $user) {
                try {
                    $user->notify($announcement);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
            // Accounts deleted since it was sent: nobody to email.
            $failed += count($this->userIds) - $users->count();
            AdminNotification::whereKey($notification->id)->incrementEach(['emails_sent' => $sent, 'emails_failed' => $failed]);
        }
    }

    /** Gave up (an error, or out of time): its people count as failed, so the Sent list stops saying "sending…". */
    public function failed(?Throwable $e): void
    {
        $columns = array_filter(['failed' => count($this->tokens), 'emails_failed' => count($this->userIds)]);
        if ($columns) AdminNotification::whereKey($this->notificationId)->incrementEach($columns);
    }
}
