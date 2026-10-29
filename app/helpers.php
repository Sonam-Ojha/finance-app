<?php

if (! function_exists('mask_email')) {
    /**
     * An email with only its first letter and its domain showing —
     * "r•••••@gmail.com" — for admin pages that list people.
     */
    function mask_email(?string $email): string
    {
        $email = trim((string) $email);
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) return $email === '' ? '' : mb_substr($email, 0, 1) . '•••••';

        return mb_substr($email, 0, 1) . '•••••' . substr($email, $at);
    }
}
