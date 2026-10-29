<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Birthday;
use Illuminate\Http\Request;

/**
 * "Complete your profile": the one step an account made with Google or
 * Apple takes on the website before anything else — its date of birth
 * (users.birthday_required, App\Http\Middleware\RequireBirthday).
 */
class CompleteProfileController extends Controller
{
    public function create(Request $request)
    {
        if (! $request->user()->birthday_required) {
            return redirect()->route('dashboard');
        }

        return view('auth.complete-profile', Birthday::pickerRange());
    }

    public function update(Request $request)
    {
        $request->validate(['birthday' => ['bail', 'required', ...Birthday::rules()]], [
            'birthday.required'    => 'Enter your date of birth.',
            'birthday.date_format' => Birthday::MESSAGE,
        ]);

        // Saving it clears birthday_required (App\Models\User::booted).
        $request->user()->update(['birthday' => $request->input('birthday')]);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
