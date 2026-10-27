<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCurrency;
use App\Support\ApiUser;
use App\Support\DefaultCategories;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email', 'unique:users,email', function ($attr, $val, $fail) {
                if (strlen(trim($val)) === 0) $fail('Enter a valid email address.');
            }],
            'password' => ['required', 'min:8', 'regex:/[a-zA-Z]/', 'regex:/\d/'],
        ], [
            'email.required'    => 'Enter a valid email address.',
            'email.email'       => 'Enter a valid email address.',
            'email.unique'      => 'An account with that email already exists.',
            'password.required' => 'Use at least 8 characters.',
            'password.min'      => 'Use at least 8 characters.',
            'password.regex'    => 'Include at least one letter and one number.',
        ]);

        $user = User::create([
            'name'      => '',
            'email'     => strtolower(trim($request->email)),
            'password'  => Hash::make($request->password),
            'onboarded' => true,
            'currency'  => 'INR',
            'theme_mode'=> 'light',
        ]);

        DefaultCategories::seed($user);

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

        return response()->noContent();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name'     => 'sometimes|max:40',
            'email'    => ['sometimes', 'email', 'unique:users,email,' . $user->id],
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

        $user->update($data);

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

        // Children first, explicitly. Left to the foreign keys, MySQL can try
        // to delete a category before the expenses that restrict it (error
        // 1451), depending on the order it runs the cascades in.
        DB::transaction(function () use ($user) {
            Budget::where('user_id', $user->id)->delete();
            AppExpense::where('user_id', $user->id)->delete();
            AppIncome::where('user_id', $user->id)->delete();
            UserCurrency::where('user_id', $user->id)->delete();
            Category::where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            // The older web app's tables still cascade from here.
            $user->delete();
        });

        // Only once the rows are gone, so a failed delete keeps the photo.
        if ($user->avatar) {
            $path = public_path($user->avatar);
            if (file_exists($path)) unlink($path);
        }

        return response()->noContent();
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|string']);

        $dataUri = $request->avatar;
        $base64  = str_contains($dataUri, ',') ? substr($dataUri, strpos($dataUri, ',') + 1) : $dataUri;

        $user = $request->user();

        $dir = public_path('avatars');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Delete old file if exists
        if ($user->avatar) {
            $old = public_path($user->avatar);
            if (file_exists($old)) unlink($old);
        }

        $filename = 'avatars/' . $user->id . '.jpg';
        file_put_contents(public_path($filename), base64_decode($base64));
        $user->update(['avatar' => $filename]);

        return response()->json([
            'data' => ['avatar_url' => url($filename)],
        ]);
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar) {
            $path = public_path($user->avatar);
            if (file_exists($path)) unlink($path);
            $user->update(['avatar' => null]);
        }
        return response()->noContent();
    }

    private function userPayload(User $user): array
    {
        return ApiUser::payload($user);
    }
}
