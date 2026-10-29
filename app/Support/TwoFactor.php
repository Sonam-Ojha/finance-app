<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Two-factor sign-in on the website: after the password (or Google), a
 * 6-digit code from an authenticator app, or one of 8 one-time recovery
 * codes. Admins turn it on in Admin › Settings. The secret is stored
 * encrypted; recovery codes only as hashes, shown in full once.
 */
class TwoFactor
{
    public const RECOVERY_CODES = 8;

    /** The half-finished sign-in waiting for its code, in the session. */
    private const PENDING = 'two_factor.login';
    private const PENDING_MINUTES = 10;
    public const MAX_TRIES = 5;

    /* ── Setting it up ──────────────────────────────────────────────── */

    /** Step one: a new secret, not in force until a code from it is confirmed. */
    public static function start(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => Totp::generateSecret(),
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step'      => null,
        ])->save();
    }

    /** Set up but not confirmed yet: the QR code is showing. */
    public static function pending(User $user): bool
    {
        return ! $user->hasTwoFactor() && ($user->getAttributes()['two_factor_secret'] ?? null) !== null;
    }

    /**
     * Step two: a code from the app proves it was set up right. Turns it on
     * and returns the recovery codes to show once; null when the code is wrong.
     *
     * @return list<string>|null
     */
    public static function confirm(User $user, string $code): ?array
    {
        if (! self::pending($user)) return null;

        $step = Totp::match($user->two_factor_secret, $code);
        if ($step === null) return null;

        $codes = self::makeRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at'   => now(),
            'two_factor_last_step'      => $step,
            'two_factor_recovery_codes' => array_map(self::hashRecoveryCode(...), $codes),
        ])->save();

        return $codes;
    }

    /** @return list<string> a fresh set, replacing the old one */
    public static function regenerateRecoveryCodes(User $user): array
    {
        $codes = self::makeRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(self::hashRecoveryCode(...), $codes)])->save();

        return $codes;
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step'      => null,
        ])->save();
    }

    public static function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /** The address the setup QR code carries. */
    public static function uri(User $user): string
    {
        $name = (string) config('app.name');
        $issuer = $name !== '' && $name !== 'Laravel' ? $name : 'Expense Tracker';

        return Totp::uri($user->two_factor_secret, $user->email, $issuer);
    }

    /* ── Checking a code ────────────────────────────────────────────── */

    /**
     * True when `$code` is a current code from the app that has not been
     * used yet, or an unused recovery code (which this uses up).
     *
     * @return 'app'|'recovery'|null which kind it was; null when wrong
     */
    public static function check(User $user, string $code): ?string
    {
        if (! $user->hasTwoFactor()) return null;

        $digits = preg_replace('/\s+/', '', $code);
        if (preg_match('/^\d{' . Totp::DIGITS . '}$/', $digits)) {
            $step = Totp::match($user->two_factor_secret, $digits);
            // The same code twice — someone watching over a shoulder — is refused.
            if ($step === null || ($user->two_factor_last_step !== null && $step <= (int) $user->two_factor_last_step)) {
                return null;
            }
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return 'app';
        }

        $hash = self::hashRecoveryCode($code);
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return 'recovery';
            }
        }

        return null;
    }

    /* ── The sign-in waiting for its code ───────────────────────────── */

    /** The password (or Google) was right: hold the sign-in until the code comes. */
    public static function holdLogin(Request $request, User $user, bool $remember = false): void
    {
        $request->session()->put(self::PENDING, [
            'id'       => $user->getKey(),
            'remember' => $remember,
            'expires'  => now()->addMinutes(self::PENDING_MINUTES)->getTimestamp(),
            'tries'    => 0,
        ]);
    }

    /** The user waiting for a code, or null when there is none or it took too long. */
    public static function heldUser(Request $request): ?User
    {
        $held = $request->session()->get(self::PENDING);
        if (! is_array($held) || ($held['expires'] ?? 0) < now()->getTimestamp()) return null;

        return User::find($held['id'] ?? null);
    }

    public static function heldRemember(Request $request): bool
    {
        return (bool) ($request->session()->get(self::PENDING)['remember'] ?? false);
    }

    /** Counts a wrong code; true once there have been too many. */
    public static function failedTry(Request $request): bool
    {
        $held = $request->session()->get(self::PENDING);
        $held['tries'] = ($held['tries'] ?? 0) + 1;
        $request->session()->put(self::PENDING, $held);

        return $held['tries'] >= self::MAX_TRIES;
    }

    public static function releaseLogin(Request $request): void
    {
        $request->session()->forget(self::PENDING);
    }

    /* ── Recovery codes ─────────────────────────────────────────────── */

    /** @return list<string> like "k3x9q-7tm2d": no 0/o or 1/l to mix up */
    private static function makeRecoveryCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $part = fn () => implode('', array_map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, 5)));

        return array_map(fn () => $part() . '-' . $part(), range(1, self::RECOVERY_CODES));
    }

    /** Case, spaces and the dash do not matter when typing one back. */
    private static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }
}
