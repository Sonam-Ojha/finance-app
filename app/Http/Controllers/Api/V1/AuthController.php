<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'Food & Drink',      'icon' => '🍔', 'color' => '#FF6B6B'],
        ['name' => 'Groceries',         'icon' => '🛒', 'color' => '#2DD4BF'],
        ['name' => 'Transport',         'icon' => '🚗', 'color' => '#38BDF8'],
        ['name' => 'Shopping',          'icon' => '🛍', 'color' => '#A78BFA'],
        ['name' => 'Bills & Utilities', 'icon' => '💡', 'color' => '#FBBF24'],
        ['name' => 'Health',            'icon' => '💊', 'color' => '#34D399'],
        ['name' => 'Entertainment',     'icon' => '🎬', 'color' => '#F472B6'],
        ['name' => 'Other',             'icon' => '✨', 'color' => '#94A3B8'],
    ];

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

        foreach (self::DEFAULT_CATEGORIES as $i => $cat) {
            Category::create([
                'user_id'    => $user->id,
                'name'       => $cat['name'],
                'icon'       => $cat['icon'],
                'color'      => $cat['color'],
                'sort_order' => $i,
            ]);
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
        $request->validate([
            'current_password' => 'required',
            'new_password'     => ['required', 'min:8', 'regex:/[a-zA-Z]/', 'regex:/\d/'],
        ], [
            'new_password.min'   => 'Use at least 8 characters.',
            'new_password.regex' => 'Include at least one letter and one number.',
        ]);

        if (! Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['Your current password is not right.']]);
        }

        $request->user()->update(['password' => Hash::make($request->new_password)]);

        return response()->noContent();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name'  => 'sometimes|max:40',
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
        ], [
            'email.email'  => 'Enter a valid email address.',
            'email.unique' => 'An account with that email already exists.',
        ]);

        $data = [];
        if ($request->has('name'))  $data['name']  = trim($request->name ?? '');
        if ($request->has('email')) $data['email'] = strtolower(trim($request->email));

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
        return [
            'id'         => $user->id,
            'name'       => $user->name ?? '',
            'email'      => $user->email,
            'onboarded'  => (bool) $user->onboarded,
            'has_pin'    => $user->has_pin,
            'currency'   => $user->currency,
            'theme_mode' => $user->theme_mode,
            'avatar_url' => $user->avatar ? url($user->avatar) : null,
        ];
    }
}
