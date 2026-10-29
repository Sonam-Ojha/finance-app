<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Ending a user's sessions: the app's sign-ins (Sanctum tokens) with the
 * phones registered under them, and the website's.
 */
class SignOut
{
    /**
     * Revokes the user's app tokens, and stops pushes to the phones that
     * were signed in with them. `$exceptTokenId` keeps one — the phone that
     * is changing the password. Without it every phone of the user goes,
     * including ones registered before tokens were recorded.
     */
    public static function apiTokens(User $user, ?int $exceptTokenId = null): void
    {
        $model = Sanctum::personalAccessTokenModel();
        $query = $model::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey());
        if ($exceptTokenId !== null) $query->whereKeyNot($exceptTokenId);

        $ids = $query->pluck('id');

        DeviceToken::whereIn('access_token_id', $ids)->delete();
        if ($exceptTokenId === null) DeviceToken::where('user_id', $user->id)->delete();

        if ($ids->isNotEmpty()) $model::whereKey($ids)->delete();
    }

    /** The token id of the current API request, when it is a stored token (not a test double or a session). */
    public static function currentTokenId(User $user): ?int
    {
        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->exists ? (int) $token->getKey() : null;
    }

    /**
     * Signs the user out of the website everywhere: their stored sessions go
     * (database session driver), and "remember me" cookies stop working.
     */
    public static function webSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    public static function everywhere(User $user): void
    {
        self::apiTokens($user);
        self::webSessions($user);
    }
}
