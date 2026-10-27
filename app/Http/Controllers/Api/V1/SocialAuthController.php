<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiUser;
use App\Support\DefaultCategories;
use App\Support\SocialTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sign in — or sign up — with Google or Apple. The app does the provider's
 * sign-in itself and sends the ID token it got back; this checks the token
 * and answers exactly like `/login`, plus `created` when the account is new.
 */
class SocialAuthController extends Controller
{
    public function google(Request $request)
    {
        if (! config('services.google.client_ids')) {
            return response()->json(['message' => 'Google sign-in is not set up yet.'], 503);
        }
        $request->validate(['id_token' => 'required|string'], [
            'id_token.required' => 'Google did not send a sign-in token. Please try again.',
        ]);

        $identity = SocialTokens::google($request->id_token);
        if (! $identity) {
            return response()->json(['message' => 'Could not sign in with Google. Please try again.'], 401);
        }

        return $this->signIn('google_id', 'Google', $identity, $identity['name']);
    }

    public function apple(Request $request)
    {
        if (! config('services.apple.client_ids')) {
            return response()->json(['message' => 'Apple sign-in is not set up yet.'], 503);
        }
        $request->validate([
            'identity_token' => 'required|string',
            'name'           => 'nullable|string|max:40',
        ], [
            'identity_token.required' => 'Apple did not send a sign-in token. Please try again.',
        ]);

        $identity = SocialTokens::apple($request->identity_token);
        if (! $identity) {
            return response()->json(['message' => 'Could not sign in with Apple. Please try again.'], 401);
        }

        return $this->signIn('apple_id', 'Apple', $identity, $request->input('name'));
    }

    private function signIn(string $column, string $provider, array $identity, ?string $name)
    {
        $user    = User::where($column, $identity['sub'])->first();
        $created = false;

        if (! $user) {
            if (! $identity['email'] || ! $identity['email_verified']) {
                return response()->json(['message' => "Your {$provider} account did not share a verified email address."], 422);
            }

            // Someone who signed up with a password and the same, verified,
            // address: the provider has just proved it is theirs, so the two
            // become one account rather than two.
            $user = User::where('email', $identity['email'])->first();

            if ($user) {
                $user->update([$column => $identity['sub']]);
            } else {
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
                    $user->forceFill(['email_verified_at' => now()])->save();
                    DefaultCategories::seed($user);

                    return $user;
                });
                $created = true;
            }
        }

        return response()->json([
            'data' => [
                'token'   => $user->createToken('api')->plainTextToken,
                'user'    => ApiUser::payload($user->fresh()),
                'created' => $created,
            ],
        ], $created ? 201 : 200);
    }
}
