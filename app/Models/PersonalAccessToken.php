<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * An app sign-in (a Sanctum token). Registered in
 * App\Providers\SecurityServiceProvider. When one is deleted — sign-out,
 * revoke — the phones registered under it stop getting pushes. Deletes that
 * skip the model (Sanctum's prune) are covered by the foreign key on
 * device_tokens.access_token_id and by `tokens:prune-idle`.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected static function booted(): void
    {
        static::deleting(function (self $token) {
            DeviceToken::where('access_token_id', $token->getKey())->delete();
        });
    }
}
