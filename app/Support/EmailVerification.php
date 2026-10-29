<?php

namespace App\Support;

use App\Models\EmailCode;
use App\Models\User;
use App\Notifications\EmailCodeNotification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Proving an account's email is real: a 6-digit code sent to it, typed back
 * into the app or the website. Shared by the API and the website.
 */
class EmailVerification
{
    public const TTL_MINUTES = 10;
    public const RESEND_SECONDS = 60;
    public const MAX_TRIES = 5;

    /**
     * Emails a code. Opening the code screen asks without `$again`: a code
     * that still works is kept, so a reload never replaces the one in the
     * inbox. "Send again" asks with it, and gets a new code — at most one a
     * minute. Says whether it sent one, and the seconds until another can be
     * sent. Throws when the mail server refuses, so the person is told
     * instead of waiting for nothing.
     *
     * @return array{sent: bool, retry_after: int}
     */
    public static function send(User $user, bool $again = false): array
    {
        if ($user->email_verified_at) return ['sent' => false, 'retry_after' => 0];

        $current = EmailCode::where('user_id', $user->id)->first();
        $wait = $current ? max(0, self::RESEND_SECONDS - (int) $current->sent_at->diffInSeconds(now(), true)) : 0;
        $usable = $current && $current->expires_at->isFuture() && $current->attempts < self::MAX_TRIES;
        if ($wait > 0 || ($usable && ! $again)) return ['sent' => false, 'retry_after' => $wait];

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            $user->notify(new EmailCodeNotification($code));
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['code' => 'We could not send the code just now. Try again in a minute.']);
        }

        EmailCode::updateOrCreate(['user_id' => $user->id], [
            'code_hash'  => self::hash($code),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'sent_at'    => now(),
        ]);

        return ['sent' => true, 'retry_after' => self::RESEND_SECONDS];
    }

    /** Marks the email verified when `$code` matches; otherwise says what went wrong. */
    public static function verify(User $user, string $code): void
    {
        if ($user->email_verified_at) return;

        $current = EmailCode::where('user_id', $user->id)->first();
        $fail = fn (string $message) => throw ValidationException::withMessages(['code' => $message]);

        if (! $current) $fail('Send yourself a code first.');
        if ($current->expires_at->isPast()) $fail('That code has expired. Send a new one.');
        if ($current->attempts >= self::MAX_TRIES) $fail('Too many wrong tries. Send a new code.');

        if (! hash_equals($current->code_hash, self::hash(preg_replace('/\D/', '', $code)))) {
            $current->increment('attempts');
            $left = self::MAX_TRIES - $current->attempts;
            $fail($left > 0
                ? "That code is not right. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.'
                : 'Too many wrong tries. Send a new code.');
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $current->delete();
    }

    /** A new email has to be proven again. */
    public static function reset(User $user): void
    {
        $user->forceFill(['email_verified_at' => null])->save();
        EmailCode::where('user_id', $user->id)->delete();
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
