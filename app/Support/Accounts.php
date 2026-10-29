<?php

namespace App\Support;

use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserCurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Profile photos and account deletion, shared by the API and the website. */
class Accounts
{
    /** The photo types a profile can use, by PHP's image type. */
    private const PHOTO_TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    /**
     * Stores a photo sent as a data URI ("data:image/jpeg;base64,…") — the
     * app crops it to 256px before sending, and so does the website. Refuses
     * anything that is not a JPEG, PNG or WebP image under 2 MB, so a broken
     * upload never replaces a good photo.
     */
    public static function storeAvatar(User $user, string $dataUri): string
    {
        $base64 = str_contains($dataUri, ',') ? substr($dataUri, strpos($dataUri, ',') + 1) : $dataUri;
        $bytes  = base64_decode($base64, true);
        $info   = $bytes !== false && strlen($bytes) <= 2 * 1024 * 1024 ? @getimagesizefromstring($bytes) : false;
        if (! $info || ! isset(self::PHOTO_TYPES[$info[2]])) {
            throw ValidationException::withMessages(['avatar' => 'Could not use that photo. Pick a JPEG or PNG image under 2 MB.']);
        }

        $dir = public_path('avatars');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Delete old file if exists
        if ($user->avatar) {
            $old = public_path($user->avatar);
            if (file_exists($old)) unlink($old);
        }

        $filename = 'avatars/' . $user->id . '.' . self::PHOTO_TYPES[$info[2]];
        file_put_contents(public_path($filename), $bytes);
        $user->update(['avatar' => $filename]);

        return self::avatarUrl($user);
    }

    public static function removeAvatar(User $user): void
    {
        if ($user->avatar) {
            $path = public_path($user->avatar);
            if (file_exists($path)) unlink($path);
            $user->update(['avatar' => null]);
        }
    }

    /**
     * The photo's address, or null when there is none to show. A photo keeps
     * its file name when it changes, so the address carries the file's time:
     * browsers and the app then fetch the new photo instead of a cached one.
     */
    public static function avatarUrl(User $user): ?string
    {
        if (! $user->avatar) return null;
        if (preg_match('#^https?://#', $user->avatar)) return $user->avatar;

        $path = public_path($user->avatar);
        clearstatcache(true, $path);
        // A missing file (lost in a server move, say) shows initials, not a broken image.
        if (! is_file($path)) return null;

        return url($user->avatar) . '?v=' . filemtime($path);
    }

    /** Two letters for a user without a photo: first and last name, or the start of one name or the email. */
    public static function initials(User $user): string
    {
        $words = preg_split('/\s+/', trim($user->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        return count($words) > 1
            ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
            : mb_strtoupper(mb_substr($words[0] ?? ($user->email ?? '?'), 0, 2));
    }

    /** The user and everything they recorded. The caller checks the password. */
    public static function delete(User $user): void
    {
        // Children first, explicitly. Left to the foreign keys, MySQL can try
        // to delete a category before the expenses that restrict it (error
        // 1451), depending on the order it runs the cascades in.
        $hadReceipts = AppExpense::where('user_id', $user->id)->whereNotNull('receipt_path')->exists();
        DB::transaction(function () use ($user) {
            Budget::where('user_id', $user->id)->delete();
            AppExpense::where('user_id', $user->id)->delete();
            AppIncome::where('user_id', $user->id)->delete();
            UserCurrency::where('user_id', $user->id)->delete();
            Category::where('user_id', $user->id)->delete();
            // ── Groups: out of every group; a group left with nobody in it goes
            // too (its expenses and payments by foreign key). Their part in
            // the others' ledgers stays, as "Former member" (App\Support\Groups).
            $groupIds = \App\Models\GroupMember::where('user_id', $user->id)->pluck('group_id')->all();
            \App\Models\GroupMember::where('user_id', $user->id)->delete();
            if ($groupIds) \App\Models\Group::whereIn('id', $groupIds)->whereDoesntHave('members')->delete();
            // ── end Groups ──
            $user->tokens()->delete();
            // The older web app's tables still cascade from here.
            $user->delete();
        });

        // Only once the rows are gone, so a failed delete keeps the photo.
        if ($user->avatar) {
            $path = public_path($user->avatar);
            if (file_exists($path)) unlink($path);
        }
        // The expenses' receipt photos (their rows went above without model events).
        if ($hadReceipts) Receipts::deleteAllFor($user);
        // Screenshots sent to support (the tickets went with the user, by foreign key).
        SupportTickets::deleteAllFor($user);
    }
}
