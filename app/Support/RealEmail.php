<?php

namespace App\Support;

use Closure;

/**
 * A first filter on sign-up emails, before any code is sent: throwaway inbox
 * services, and domains that cannot receive mail at all. The code itself
 * (App\Support\EmailVerification) is what proves the person owns the address.
 */
class RealEmail
{
    /** Well-known throwaway inbox services. */
    public const THROWAWAY = [
        '10minutemail.com', '10minutemail.net', '20minutemail.com', 'anonaddy.me', 'burnermail.io',
        'discard.email', 'dispostable.com', 'dropmail.me', 'emailondeck.com', 'fakeinbox.com',
        'fakemail.net', 'getairmail.com', 'getnada.com', 'guerrillamail.biz', 'guerrillamail.com',
        'guerrillamail.de', 'guerrillamail.info', 'guerrillamail.net', 'guerrillamail.org', 'guerrillamailblock.com',
        'harakirimail.com', 'inboxbear.com', 'mail.tm', 'mail-temp.com', 'mailcatch.com',
        'maildrop.cc', 'mailinator.com', 'mailinator.net', 'mailnesia.com', 'mailpoof.com',
        'mintemail.com', 'moakt.com', 'mohmal.com', 'mytemp.email', 'nada.email',
        'sharklasers.com', 'spam4.me', 'spambox.us', 'spamgourmet.com', 'temp-mail.io',
        'temp-mail.org', 'tempail.com', 'tempmail.com', 'tempmail.dev', 'tempmail.net',
        'tempmailo.com', 'tempr.email', 'throwawaymail.com', 'trashmail.com', 'trashmail.de',
        'yopmail.com', 'yopmail.fr', 'yopmail.net', 'emailfake.com', 'fakemailgenerator.com',
    ];

    /** A sentence for an address that will never get the code, or null when it might. */
    public static function problem(string $email): ?string
    {
        $domain = strtolower(trim(substr(strrchr($email, '@') ?: '', 1)));
        if ($domain === '') return null; // the `email` rule reports a malformed address

        if (in_array($domain, self::THROWAWAY, true)) {
            return 'Use your own email address. Temporary inboxes cannot be used.';
        }

        // A typo, a made-up domain, or one that says it takes no mail.
        if (config('services.email_check.dns') && ! self::acceptsMail($domain)) {
            return "No email can reach {$domain}. Check the address for typos.";
        }

        return null;
    }

    /**
     * Whether the domain has somewhere to deliver mail: its MX records, or
     * failing those its own address. A lone MX of "." (RFC 7505 "null MX")
     * means no mail at all — example.com publishes exactly that, and two
     * live accounts on it bounced an announcement.
     */
    private static function acceptsMail(string $domain): bool
    {
        $mx = @dns_get_record($domain . '.', DNS_MX) ?: [];
        if ($mx) {
            $targets = array_map(fn (array $record) => rtrim($record['target'] ?? '', '.'), $mx);

            return array_filter($targets) !== [];
        }

        return checkdnsrr($domain . '.', 'A');
    }

    /** As a validation rule. */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && ($problem = self::problem($value))) $fail($problem);
        };
    }
}
