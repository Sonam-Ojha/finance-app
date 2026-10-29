<?php

namespace App\Support;

use App\Models\User;

class ApiUser
{
    /** The user object in every auth response, `/me` and `/bootstrap`. */
    public static function payload(User $user): array
    {
        return [
            'id'                  => $user->id,
            'name'                => $user->name ?? '',
            'email'               => $user->email,
            // False until the 6-digit code is typed back (App\Support\EmailVerification).
            'email_verified'      => $user->email_verified_at !== null,
            'onboarded'           => (bool) $user->onboarded,
            'has_pin'             => $user->has_pin,
            'has_password'        => $user->has_password,
            'currency'            => $user->currency,
            'theme_mode'          => $user->theme_mode,
            'avatar_url'          => Accounts::avatarUrl($user),
            'birthday'            => $user->birthday?->toDateString(),
            // True for an account Google or Apple sign-in just made: the app
            // asks for the date of birth before going on. Saving one clears it.
            'birthday_required'   => (bool) $user->birthday_required,
            'email_notifications' => (bool) ($user->email_notifications ?? true),
            // No reminder fields: Admin › App control sets the reminders for
            // everyone, and an app that finds none shows no Reminders screen.
        ];
    }
}
