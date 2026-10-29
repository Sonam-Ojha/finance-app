<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiUser;
use App\Support\SocialSignIn;
use App\Support\SocialSignInRefused;
use App\Support\SocialTokens;
use Illuminate\Http\Request;

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

    /** Linking, claiming and creating accounts: App\Support\SocialSignIn, shared with the website. */
    private function signIn(string $column, string $provider, array $identity, ?string $name)
    {
        try {
            [$user, $created] = SocialSignIn::resolve($column, $provider, $identity, $name);
        } catch (SocialSignInRefused $refused) {
            // `code` only where there is one (account_blocked): the older answers keep their shape.
            return response()->json(array_filter([
                'message' => $refused->getMessage(),
                'code'    => $refused->errorCode,
            ]), $refused->status);
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
