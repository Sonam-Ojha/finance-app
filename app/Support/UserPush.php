<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\User;

/** A push to one user's phones — the reminders and alerts the server sends on its own. */
class UserPush
{
    /**
     * Quietly does nothing without Firebase or a signed-in phone.
     *
     * @param array<string, string> $data `type` tells the app what it is about
     * @return int phones it reached
     */
    public static function send(User $user, string $title, string $body, array $data = []): int
    {
        if (! Fcm::configured()) return 0;
        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
        if (! $tokens) return 0;

        $result = Fcm::send($tokens, $title, $body, $data);
        // Tokens from phones that removed the app: no use keeping them.
        if ($result['invalid']) DeviceToken::whereIn('token', $result['invalid'])->delete();

        return $result['delivered'];
    }
}
