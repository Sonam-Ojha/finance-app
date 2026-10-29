<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiUser;
use App\Support\EmailVerification;
use Illuminate\Http\Request;

/** The app's "check your email" screen: send a code, then type it back. */
class EmailVerificationController extends Controller
{
    /**
     * Safe to call on every visit to the screen: a code that still works is
     * kept. `again: true` is the "Send again" button — a new code, at most
     * one a minute.
     */
    public function send(Request $request)
    {
        $result = EmailVerification::send($request->user(), $request->boolean('again'));

        return response()->json(['data' => $result + ['email' => $request->user()->email]]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:12'], ['code.required' => 'Enter the 6-digit code.']);
        EmailVerification::verify($request->user(), $request->input('code'));

        return response()->json(['data' => ApiUser::payload($request->user()->fresh())]);
    }
}
