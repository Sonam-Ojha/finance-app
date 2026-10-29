<?php

namespace App\Support;

use App\Models\EmailCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who a checked Google or Apple identity (App\Support\SocialTokens) signs in
 * as — shared by the API and the website:
 *
 *  - the account already linked to it;
 *  - else the account with the same email: linked as it is when that account
 *    proved its email, or claimed when it never did — the provider has just
 *    proved the address belongs to the caller, so whoever registered it
 *    without owning it loses it (password, PIN, sessions, phones);
 *  - else a new account, email verified, with the default categories.
 *
 * A blocked account is refused at every step.
 */
class SocialSignIn
{
    public const BLOCKED_MESSAGE = 'This account is blocked. Contact support.';

    /**
     * @param 'google_id'|'apple_id' $column
     * @param array{sub: string, email: ?string, email_verified: bool, name: ?string} $identity
     * @return array{0: User, 1: bool} the account, and whether it was just made
     *
     * @throws SocialSignInRefused
     */
    public static function resolve(string $column, string $provider, array $identity, ?string $name): array
    {
        $user = User::where($column, $identity['sub'])->first();
        if ($user) {
            self::refuseBlocked($user);

            return [$user, false];
        }

        if (! $identity['email'] || ! $identity['email_verified']) {
            throw new SocialSignInRefused("Your {$provider} account did not share a verified email address.", 422);
        }

        $user = User::where('email', $identity['email'])->first();
        if ($user) {
            self::refuseBlocked($user);

            if ($user->email_verified_at) {
                // Someone who signed up with a password and proved the same
                // address: one account rather than two.
                $user->update([$column => $identity['sub']]);
            } else {
                self::claim($user, $column, $identity['sub']);
            }

            return [$user->fresh(), false];
        }

        $user = DB::transaction(function () use ($column, $identity, $name) {
            $user = User::create([
                'name'       => mb_substr(trim($name ?? ''), 0, 40),
                'email'      => $identity['email'],
                'password'   => null,
                'onboarded'  => true,
                'currency'   => 'INR',
                'theme_mode' => 'light',
                $column      => $identity['sub'],
            ]);
            // No date of birth from the provider: the website asks next, and
            // the app reads `birthday_required`.
            $user->forceFill(['email_verified_at' => now(), 'birthday_required' => true])->save();
            DefaultCategories::seed($user);

            return $user;
        });

        return [$user, true];
    }

    /** The unproven account becomes the caller's, and only the caller's. */
    private static function claim(User $user, string $column, string $sub): void
    {
        DB::transaction(function () use ($user, $column, $sub) {
            SignOut::everywhere($user);
            // Any other sign-in it was linked to goes too; then this one.
            $user->forceFill([
                'password'          => null,
                'pin'               => null,
                'google_id'         => null,
                'apple_id'          => null,
                'email_verified_at' => now(),
                $column             => $sub,
            ])->save();
            EmailCode::where('user_id', $user->id)->delete();
        });
    }

    private static function refuseBlocked(User $user): void
    {
        if ($user->isBlocked()) {
            throw new SocialSignInRefused(self::BLOCKED_MESSAGE, 403, 'account_blocked');
        }
    }
}
