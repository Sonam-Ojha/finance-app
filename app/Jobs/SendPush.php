<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\UserPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A push to one user's phones (App\Support\UserPush), sent from the queue so
 * a slow Firebase never holds up a request. The scheduler runs the queue
 * every minute (routes/console.php). A user who has since deleted their
 * account is skipped.
 */
class SendPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Below the database queue's retry_after (90 s), so a slow run is never picked up twice. */
    public int $timeout = 75;

    /** @param array<string, string> $data `type` tells the app what it is about */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if ($user) UserPush::send($user, $this->title, $this->body, $this->data);
    }
}
