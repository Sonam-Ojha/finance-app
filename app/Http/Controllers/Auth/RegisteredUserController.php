<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Birthday;
use App\Support\DefaultCategories;
use App\Support\EmailVerification;
use App\Support\RealEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', Birthday::pickerRange());
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class, RealEmail::rule()],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // The profile's rule: at least 1 and at most 120 years old.
            'birthday' => ['bail', 'required', ...Birthday::rules()],
        ], [
            'birthday.required'    => 'Enter your date of birth.',
            'birthday.date_format' => Birthday::MESSAGE,
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'birthday' => $request->birthday,
        ]);
        // The same starting categories as an account made in the app.
        DefaultCategories::seed($user);

        event(new Registered($user));

        Auth::login($user);

        // The code page comes next (the email.verified middleware); if the
        // mail server is down, its "Send again" retries.
        try {
            EmailVerification::send($user);
        } catch (ValidationException) {
        }

        return redirect(route('verification.notice', absolute: false));
    }
}
