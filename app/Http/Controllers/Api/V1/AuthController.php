<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RejectBlockedUsers;
use App\Models\User;
use App\Support\Accounts;
use App\Support\ApiUser;
use App\Support\Birthday;
use App\Support\DefaultCategories;
use App\Support\EmailVerification;
use App\Support\RealEmail;
use App\Support\SignOut;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // A blocked account's address stays blocked, rather than "taken".
        if (is_string($request->email) && User::where('email', strtolower(trim($request->email)))->whereNotNull('blocked_at')->exists()) {
            return $this->blocked();
        }

        $request->validate([
            'email'    => ['bail', 'required', 'email', 'unique:users,email', function ($attr, $val, $fail) {
                if (strlen(trim($val)) === 0) $fail('Enter a valid email address.');
            }, RealEmail::rule()],
            'password' => ['required', 'min:8', 'regex:/[a-zA-Z]/', 'regex:/\d/'],
            // Optional: the 1.0 app does not send it; newer ones require it in their form.
            'birthday' => ['bail', 'nullable', ...Birthday::rules()],
        ], [
            'email.required'       => 'Enter a valid email address.',
            'email.email'          => 'Enter a valid email address.',
            'email.unique'         => 'An account with that email already exists.',
            'password.required'    => 'Use at least 8 characters.',
            'password.min'         => 'Use at least 8 characters.',
            'password.regex'       => 'Include at least one letter and one number.',
            'birthday.date_format' => Birthday::MESSAGE,
        ]);

        $user = User::create([
            'name'      => '',
            'email'     => strtolower(trim($request->email)),
            'password'  => Hash::make($request->password),
            'onboarded' => true,
            'currency'  => 'INR',
            'theme_mode'=> 'light',
            'birthday'  => $request->input('birthday') ?: null,
        ]);

        DefaultCategories::seed($user);

        // The code is on its way while the app shows the screen to type it
        // in. If the mail server is down, that screen's "Send again" retries.
        try {
            EmailVerification::send($user);
        } catch (ValidationException) {
        }

        return response()->json([
            'data' => [
                'token' => $user->createToken('api')->plainTextToken,
                'user'  => $this->userPayload($user),
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim($request->email));
        $user  = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No account uses that email.']]);
        }

        if (! $user->has_password) {
            throw ValidationException::withMessages(['email' => ['This account signs in with Google or Apple.']]);
        }

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Incorrect password.']]);
        }

        if ($user->isBlocked()) {
            return $this->blocked();
        }

        return response()->json([
            'data' => [
                'token' => $user->createToken('api')->plainTextToken,
                'user'  => $this->userPayload($user),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    public function updatePassword(Request $request)
    {
        // An account made with Google or Apple has no current password; this
        // is how it gets its first one.
        $hasPassword = $request->user()->has_password;
        $request->validate([
            'current_password' => $hasPassword ? 'required' : 'nullable',
            'new_password'     => ['required', 'min:8', 'regex:/[a-zA-Z]/', 'regex:/\d/'],
        ], [
            'new_password.min'   => 'Use at least 8 characters.',
            'new_password.regex' => 'Include at least one letter and one number.',
        ]);

        if ($hasPassword && ! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['Your current password is not right.']]);
        }

        $request->user()->update(['password' => Hash::make($request->new_password)]);

        // Every other phone signed in to this account is signed out; this one stays.
        SignOut::apiTokens($request->user(), SignOut::currentTokenId($request->user()));

        return response()->noContent();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name'     => 'sometimes|max:40',
            'email'    => ['bail', 'sometimes', 'email', 'unique:users,email,' . $user->id, RealEmail::rule()],
            // bail: the age check below only makes sense for a well-formed date.
            'birthday' => ['bail', 'sometimes', 'nullable', 'date_format:Y-m-d', function ($attr, $value, $fail) {
                // At least 1 and at most 120 years old, as the app checks. A
                // day of slack either way: the app judges by the user's own
                // date, which can be a day off the server's.
                $born = Carbon::createFromFormat('!Y-m-d', $value);
                if ($born->gt(now()->addDay()->subYear()) || $born->lte(now()->subDay()->subYears(121))) {
                    $fail('Choose a real date of birth.');
                }
            }],
        ], [
            'email.email'          => 'Enter a valid email address.',
            'email.unique'         => 'An account with that email already exists.',
            'birthday.date_format' => 'Choose a real date of birth.',
        ]);

        $data = [];
        if ($request->has('name'))  $data['name']  = trim($request->name ?? '');
        if ($request->has('email')) $data['email'] = strtolower(trim($request->email));
        // Absent keeps it — older app versions send only name and email.
        if ($request->has('birthday')) $data['birthday'] = $request->birthday;

        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;
        $user->update($data);

        // A new address has to be proven like the first one was.
        if ($emailChanged) {
            EmailVerification::reset($user);
            try {
                EmailVerification::send($user);
            } catch (ValidationException) {
            }
        }

        return response()->json(['data' => $this->userPayload($user->fresh())]);
    }

    public function markOnboarded(Request $request)
    {
        $request->user()->update(['onboarded' => true]);
        return response()->noContent();
    }

    public function setPin(Request $request)
    {
        $request->validate(['pin' => ['required', 'regex:/^\d{4}$/']], ['pin.regex' => 'Enter all four digits.']);
        $request->user()->update(['pin' => Hash::make($request->pin)]);
        return response()->noContent();
    }

    public function removePin(Request $request)
    {
        $request->user()->update(['pin' => null]);
        return response()->noContent();
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // An account made with Google or Apple has no password to confirm
        // with; the signed-in token is the proof there.
        if ($user->has_password) {
            $request->validate(['password' => 'required'], [
                'password.required' => 'Enter your password to confirm.',
            ]);

            if (! Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Incorrect password.'], 422);
            }
        }

        Accounts::delete($user);

        return response()->noContent();
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|string']);

        return response()->json([
            'data' => ['avatar_url' => Accounts::storeAvatar($request->user(), $request->avatar)],
        ]);
    }

    public function removeAvatar(Request $request)
    {
        Accounts::removeAvatar($request->user());
        return response()->noContent();
    }

    private function userPayload(User $user): array
    {
        return ApiUser::payload($user);
    }

    /** The same answer RejectBlockedUsers gives a blocked account's token. */
    private function blocked()
    {
        return RejectBlockedUsers::response();
    }
}
