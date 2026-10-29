<?php

namespace App\Support;

use App\Models\User;

/**
 * The sidebar colours a user can pick (Settings → Appearance, and Admin →
 * Settings). One choice per account, used by both the admin and the
 * personal view. Each is dark enough for white text (7.5:1 or more); the
 * cyan eyebrow only stays where it still reads (4.5:1), others use white.
 */
final class SidebarColors
{
    public const DEFAULT = 'navy';

    /** key => [label, background, eyebrow colour] */
    public const ALL = [
        'navy'     => ['Navy', '#002e62', '#00a7cb'],
        'midnight' => ['Midnight', '#0f172a', '#00a7cb'],
        'indigo'   => ['Indigo', '#3730a3', 'rgba(255,255,255,.8)'],
        'ocean'    => ['Ocean', '#075985', 'rgba(255,255,255,.8)'],
        'teal'     => ['Teal', '#115e59', 'rgba(255,255,255,.8)'],
        'forest'   => ['Forest', '#14532d', 'rgba(255,255,255,.8)'],
        'plum'     => ['Plum', '#581c87', 'rgba(255,255,255,.8)'],
        'wine'     => ['Wine', '#881337', 'rgba(255,255,255,.8)'],
        'rust'     => ['Rust', '#7c2d12', 'rgba(255,255,255,.8)'],
        'graphite' => ['Graphite', '#374151', 'rgba(255,255,255,.8)'],
    ];

    public static function key(?User $user): string
    {
        $key = $user?->sidebar_color;

        return isset(self::ALL[$key]) ? $key : self::DEFAULT;
    }

    /** [label, background, eyebrow colour] for the user's choice. */
    public static function for(?User $user): array
    {
        return self::ALL[self::key($user)];
    }
}
