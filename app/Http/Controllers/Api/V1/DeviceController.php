<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Support\SignOut;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The app registers the phone's Firebase push token after signing in, and
 * removes it on signing out, so pushes reach only signed-in phones.
 */
class DeviceController extends Controller
{
    /** POST /me/devices { token, platform } */
    public function store(Request $request)
    {
        $request->validate([
            'token'    => 'required|string|max:512',
            'platform' => ['nullable', Rule::in(DeviceToken::PLATFORMS)],
        ]);

        // A token is one phone: if someone else was signed in on it, it is
        // theirs no longer. It is tied to this sign-in, and goes when it ends.
        DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id'         => $request->user()->id,
                'access_token_id' => SignOut::currentTokenId($request->user()),
                'platform'        => $request->input('platform', 'android'),
            ],
        );

        return response()->noContent();
    }

    /** DELETE /me/devices { token } */
    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string|max:512']);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        return response()->noContent();
    }
}
