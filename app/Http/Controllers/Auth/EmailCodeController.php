<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\EmailVerification;
use App\Support\RealEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The website's "check your email" page: every signed-in page sends an
 * unverified account here (the email.verified middleware) until the 6-digit
 * code is typed back. The app has the same screen (Api\V1\EmailVerificationController).
 */
class EmailCodeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) return redirect()->route('dashboard');

        // A code that still works is kept, so reloading this page never replaces it.
        try {
            $result = EmailVerification::send($user);
        } catch (ValidationException $e) {
            $result = ['sent' => false, 'retry_after' => 0];
            $sendError = $e->getMessage();
        }

        return view('auth.verify-email', [
            'email'      => $user->email,
            'retryAfter' => $result['retry_after'],
            'sendError'  => $sendError ?? null,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:12'], ['code.required' => 'Enter the 6-digit code.']);
        EmailVerification::verify($request->user(), $request->input('code'));

        return redirect()->intended(route('dashboard'))->with('success', 'Email verified. Welcome to Expense Tracker!');
    }

    public function resend(Request $request)
    {
        $result = EmailVerification::send($request->user(), again: true);

        return back()->with('status', $result['sent']
            ? 'A new code is on its way. The earlier one no longer works.'
            : "Wait {$result['retry_after']} seconds before asking for another code.");
    }

    /** A typo in the address: fix it here and the code goes to the new one. */
    public function changeEmail(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => strtolower(trim((string) $request->email))]);
        $request->validate([
            'email' => ['bail', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id), RealEmail::rule()],
        ], [
            'email.required' => 'Enter a valid email address.',
            'email.email'    => 'Enter a valid email address.',
            'email.unique'   => 'An account with that email already exists.',
        ]);

        if ($request->email !== $user->email) {
            $user->forceFill(['email' => $request->email])->save();
            EmailVerification::reset($user);
            EmailVerification::send($user);
        }

        return redirect()->route('verification.notice')->with('status', "Code sent to {$user->email}.");
    }
}
