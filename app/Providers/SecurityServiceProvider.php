<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * How long the app's sign-ins (Sanctum tokens) last: config/sanctum.php's
 * `expiration` counts from the sign-in; `inactivity_expiration`, checked
 * here, from the last time the token was used.
 */
class SecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid): bool {
            if (! $isValid) return false;

            $minutes = (int) config('sanctum.inactivity_expiration');
            if ($minutes <= 0) return true;

            $lastActive = $token->last_used_at ?? $token->created_at;

            return $lastActive !== null && $lastActive->gt(now()->subMinutes($minutes));
        });
    }
}
