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
            'email_notifications' => (bool) ($user->email_notifications ?? true),
            // Settings › Reminders. A null time means the daily reminder is off.
            'daily_reminder_at'   => $user->daily_reminder_at,
            'budget_alert_push'   => (bool) ($user->budget_alert_push ?? true),
            'monthly_summary'     => (bool) ($user->monthly_summary ?? true),
        ];
    }
}
