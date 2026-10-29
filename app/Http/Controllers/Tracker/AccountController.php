<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Support\Accounts;
use App\Support\EmailVerification;
use App\Support\RealEmail;
use App\Support\SignOut;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The app's Account information and Change password screens. Same rules as the API. */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('tracker.profile', [
            'user'      => $user,
            'avatarUrl' => Accounts::avatarUrl($user),
            'oldest'    => now()->subYears(120)->startOfYear()->toDateString(),
            'youngest'  => now()->subYear()->toDateString(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => strtolower(trim((string) $request->email))]);
        $request->validate([
            // Blank is allowed (the form trims it to nothing), as in the app.
            'name'     => ['nullable', 'max:40'],
            'email'    => ['bail', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id), RealEmail::rule()],
            // At least 1 and at most 120 years old, as the app checks.
            'birthday' => ['bail', 'nullable', 'date_format:Y-m-d', function ($attr, $value, $fail) {
                $born = Carbon::createFromFormat('!Y-m-d', $value);
                if ($born->gt(now()->addDay()->subYear()) || $born->lte(now()->subDay()->subYears(121))) {
                    $fail('Choose a real date of birth.');
                }
            }],
        ], [
            'name.max'             => 'Keep your name to 40 characters.',
            'email.required'       => 'Enter a valid email address.',
            'email.email'          => 'Enter a valid email address.',
            'email.unique'         => 'An account with that email already exists.',
            'birthday.date_format' => 'Choose a real date of birth.',
        ]);

        $user->fill([
            'name'     => trim((string) $request->name),
            'email'    => $request->email,
            'birthday' => $request->birthday ?: null,
        ]);
        $emailChanged = $user->isDirty('email');
        $user->save();

        // A new address has to be proven like the first one was: the code
        // goes out now, and the code page comes up next.
        if ($emailChanged) {
            EmailVerification::reset($user);
            try {
                EmailVerification::send($user);
            } catch (ValidationException) {
            }

            return redirect()->route('verification.notice');
        }

        return redirect()->route('profile.edit')->with('success', 'Account details saved');
    }

    /** A photo is saved straight away, apart from the form's Save. */
    public function storePhoto(Request $request)
    {
        $request->validate(['avatar' => ['required', 'string', 'starts_with:data:image/']], [
            'avatar.*' => 'Could not use that photo. Try a different one.',
        ]);
        Accounts::storeAvatar($request->user(), $request->avatar);

        return redirect()->route('profile.edit')->with('success', 'Photo saved');
    }

    public function destroyPhoto(Request $request)
    {
        Accounts::removeAvatar($request->user());

        return redirect()->route('profile.edit')->with('success', 'Photo removed');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        // An account made with Google or Apple has no password to confirm with.
        if ($user->has_password) {
            $request->validateWithBag('userDeletion', ['password' => 'required'], ['password.required' => 'Enter your password to confirm.']);
            if (! Hash::check($request->password, $user->password)) {
                return back()->withErrors(['password' => 'Incorrect password.'], 'userDeletion');
            }
        }

        Auth::logout();
        Accounts::delete($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function password(Request $request)
    {
        return view('tracker.password', ['hasPassword' => $request->user()->has_password]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $hasPassword = $user->has_password;
        $request->validate([
            'current_password'          => $hasPassword ? 'required' : 'nullable',
            'new_password'              => ['required', 'min:8', 'regex:/[a-zA-Z]/', 'regex:/\d/', 'confirmed'],
        ], [
            'current_password.required' => 'Enter your current password.',
            'new_password.required'     => 'Use at least 8 characters.',
            'new_password.min'          => 'Use at least 8 characters.',
            'new_password.regex'        => 'Include at least one letter and one number.',
            'new_password.confirmed'    => 'The two new passwords do not match.',
        ]);

        if ($hasPassword && ! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is not right.']);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        // The app's sign-ins (and their phones' pushes) end with the old password.
        SignOut::apiTokens($user);

        return redirect()->route('settings.index')->with('success', $hasPassword ? 'Password changed' : 'Password set');
    }
}
