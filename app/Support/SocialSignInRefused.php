<?php

namespace App\Support;

use RuntimeException;

/** Why a Google or Apple sign-in was turned away (App\Support\SocialSignIn). */
class SocialSignInRefused extends RuntimeException
{
    /** @param string|null $errorCode machine-readable, for the API's `code` (e.g. account_blocked) */
    public function __construct(string $message, public readonly int $status, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
