<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SocialSignIn;
use App\Support\SocialSignInRefused;
use App\Support\SocialTokens;
use App\Support\WebSignIn;
use Illuminate\Http\Request;

/**
 * "Continue with Google" on the sign-in and sign-up pages. Google Identity
 * Services gives the browser an ID token; it is posted here (with the CSRF
 * token), checked exactly as the app's (App\Support\SocialTokens), and
 * signs in — or makes, links or claims the account — by the same rules
 * (App\Support\SocialSignIn).
 */
class GoogleSignInController extends Controller
{
    public function store(Request $request)
    {
        // Back to the sign-in or sign-up page the button was on.
        $fail = fn (string $message) => redirect()->back(302, [], route('login'))->withErrors(['google' => $message]);

        if (! config('services.google.web_client_id')) {
            return $fail('Google sign-in is not available right now. Use your email and password.');
        }
        if (! is_string($request->input('credential')) || $request->input('credential') === '') {
            return $fail('Google did not send a sign-in token. Please try again.');
        }

        $identity = SocialTokens::google($request->input('credential'));
        if (! $identity) {
            return $fail('Could not sign in with Google. Please try again.');
        }

        try {
            [$user] = SocialSignIn::resolve('google_id', 'Google', $identity, $identity['name']);
        } catch (SocialSignInRefused $refused) {
            return $fail($refused->getMessage());
        }

        return WebSignIn::proceed($request, $user);
    }
}
