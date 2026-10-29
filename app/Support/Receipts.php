<?php

namespace App\Support;

use App\Models\AppExpense;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receipt photos on expenses, shared by the API and the website. Files are
 * private: on the `receipts` disk (storage/app/receipts/{user_id}/…), never
 * under public/, and read only through routes that check the expense is the
 * caller's own. `app_expenses.receipt_path` is the file's path on that disk.
 */
class Receipts
{
    public const DISK = 'receipts';

    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MESSAGE = 'Could not use that receipt. Pick a JPEG, PNG or WebP image under 5 MB.';

    /** The types a receipt can be, by PHP's image type. */
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    private const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /**
     * The image in a data URI ("data:image/jpeg;base64,…", or bare base64)
     * as [bytes, extension]. Anything that is not a JPEG, PNG or WebP image
     * of at most 5 MB is refused with a message under `$field`.
     *
     * @return array{0: string, 1: string}
     */
    public static function decode(?string $dataUri, string $field = 'receipt'): array
    {
        $dataUri = (string) $dataUri;
        $base64  = str_contains($dataUri, ',') ? substr($dataUri, strpos($dataUri, ',') + 1) : $dataUri;
        $bytes   = base64_decode($base64, true);
        $info    = $bytes !== false && $bytes !== '' && strlen($bytes) <= self::MAX_BYTES ? @getimagesizefromstring($bytes) : false;
        if (! $info || ! isset(self::TYPES[$info[2]])) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }

        return [$bytes, self::TYPES[$info[2]]];
    }

    /**
     * Stores the photo as the expense's receipt, replacing any old one. A
     * new file name each time, so a cached old photo is never shown again.
     */
    public static function store(AppExpense $expense, string $dataUri, string $field = 'receipt'): void
    {
        [$bytes, $extension] = self::decode($dataUri, $field);
        self::put($expense, $bytes, $extension);
    }

    /** Writes already-checked bytes (from decode()) as the expense's receipt. */
    public static function put(AppExpense $expense, string $bytes, string $extension): void
    {
        $path = "{$expense->user_id}/{$expense->id}-" . Str::lower(Str::random(10)) . ".{$extension}";
        Storage::disk(self::DISK)->put($path, $bytes);

        $old = $expense->receipt_path;
        $expense->receipt_path = $path;
        $expense->save();

        if ($old && $old !== $path) self::deleteFile($old);
    }

    public static function remove(AppExpense $expense): void
    {
        if (! $expense->receipt_path) return;

        $old = $expense->receipt_path;
        $expense->receipt_path = null;
        $expense->save();
        self::deleteFile($old);
    }

    public static function deleteFile(?string $path): void
    {
        if ($path) Storage::disk(self::DISK)->delete($path);
    }

    /** Every receipt a user has, once their account is gone. */
    public static function deleteAllFor(User $user): void
    {
        Storage::disk(self::DISK)->deleteDirectory((string) $user->id);
    }

    /** The photo itself; 404 when the expense has none (or its file is lost). */
    public static function response(AppExpense $expense): Response
    {
        $path = $expense->receipt_path;
        $disk = Storage::disk(self::DISK);
        if (! $path || ! $disk->exists($path)) abort(404, 'This expense has no receipt.');

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $disk->response($path, 'receipt-' . $expense->id . '.' . $extension, [
            'Content-Type'  => self::MIME[$extension] ?? 'application/octet-stream',
            // Only ever the owner's. An address from url() names this very photo
            // (?v= changes with it), so it may be kept a day; a bare one may not.
            'Cache-Control' => request()->has('v') ? 'private, max-age=86400' : 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** The website's address for the photo; changes whenever the photo does. */
    public static function url(AppExpense $expense): ?string
    {
        if (! $expense->receipt_path) return null;

        return route('expenses.receipt', ['expense' => $expense->id, 'v' => substr(md5($expense->receipt_path), 0, 8)]);
    }
}
