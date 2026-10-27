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
            'onboarded'           => (bool) $user->onboarded,
            'has_pin'             => $user->has_pin,
            'has_password'        => $user->has_password,
            'currency'            => $user->currency,
            'theme_mode'          => $user->theme_mode,
            'avatar_url'          => $user->avatar ? url($user->avatar) : null,
            'birthday'            => $user->birthday?->toDateString(),
            'email_notifications' => (bool) ($user->email_notifications ?? true),
        ];
    }
}
