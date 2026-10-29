<?php

namespace App\Support;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCurrency;
use Illuminate\Support\Facades\DB;

/** Profile photos and account deletion, shared by the API and the website. */
class Accounts
{
    /**
     * Stores a photo sent as a data URI ("data:image/jpeg;base64,…") — the
     * app crops it to 256px before sending, and so does the website.
     */
    public static function storeAvatar(User $user, string $dataUri): string
    {
        $base64 = str_contains($dataUri, ',') ? substr($dataUri, strpos($dataUri, ',') + 1) : $dataUri;

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

        return url($filename);
    }

    public static function removeAvatar(User $user): void
    {
        if ($user->avatar) {
            $path = public_path($user->avatar);
            if (file_exists($path)) unlink($path);
            $user->update(['avatar' => null]);
        }
    }

    public static function avatarUrl(User $user): ?string
    {
        return $user->avatar ? url($user->avatar) : null;
    }

    /** The user and everything they recorded. The caller checks the password. */
    public static function delete(User $user): void
    {
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
    }
}
