<?php

namespace App\Support;

use App\Jobs\SendPush;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportReplied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Help & support, shared by the website (My account › Help & support), the
 * API (/api/v1/support) and Admin › Support. The one place tickets and their
 * messages are written.
 *
 * A ticket is `open` while it waits for support, `answered` once support
 * replied last, and `closed` when an admin closes it; a new message from the
 * user opens it again. Screenshots are private, on the `support` disk
 * (storage/app/support/{user_id}/…), and only reach the ticket's user and
 * admins through routes that check.
 */
class SupportTickets
{
    public const DISK = 'support';

    public const MAX_BYTES = 5 * 1024 * 1024;

    public const SCREENSHOT_MESSAGE = 'Could not use that screenshot. Pick a JPEG, PNG or WebP image under 5 MB.';

    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    private const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /** Validation for a new request, from either side. */
    public static function rules(): array
    {
        return [
            'subject' => 'required|string|max:120',
            'message' => 'required|string|max:5000',
        ];
    }

    public static function messages(): array
    {
        return [
            'subject.required' => 'Give your request a subject.',
            'subject.max'      => 'Keep the subject to 120 characters.',
            'message.required' => 'Tell us what happened.',
            'message.max'      => 'Keep the message to 5,000 characters.',
        ];
    }

    /**
     * An image sent as a data URI ("data:image/png;base64,…", or bare
     * base64), as [bytes, extension]. Anything but a JPEG, PNG or WebP image
     * of at most 5 MB is refused under `$field`.
     *
     * @return array{0: string, 1: string}
     */
    public static function decodeScreenshot(?string $dataUri, string $field = 'screenshot'): array
    {
        $dataUri = (string) $dataUri;
        $base64  = str_contains($dataUri, ',') ? substr($dataUri, strpos($dataUri, ',') + 1) : $dataUri;
        $bytes   = base64_decode($base64, true);

        return self::checkImage($bytes === false ? '' : $bytes, $field);
    }

    /** @return array{0: string, 1: string} */
    public static function fromUpload(UploadedFile $file, string $field = 'screenshot'): array
    {
        $bytes = $file->isValid() && $file->getSize() <= self::MAX_BYTES ? (string) file_get_contents($file->getRealPath()) : '';

        return self::checkImage($bytes, $field);
    }

    /** @return array{0: string, 1: string} */
    private static function checkImage(string $bytes, string $field): array
    {
        $info = $bytes !== '' && strlen($bytes) <= self::MAX_BYTES ? @getimagesizefromstring($bytes) : false;
        if (! $info || ! isset(self::TYPES[$info[2]])) {
            throw ValidationException::withMessages([$field => self::SCREENSHOT_MESSAGE]);
        }

        return [$bytes, self::TYPES[$info[2]]];
    }

    /**
     * A new request: the ticket and its first message.
     *
     * @param array{0: string, 1: string}|null $screenshot from decodeScreenshot() / fromUpload()
     */
    public static function open(User $user, string $subject, string $body, ?array $screenshot = null): SupportTicket
    {
        $ticket = DB::transaction(function () use ($user, $subject, $body) {
            $ticket = SupportTicket::create([
                'user_id'         => $user->id,
                'subject'         => trim($subject),
                'status'          => SupportTicket::OPEN,
                'last_reply_from' => SupportMessage::FROM_USER,
            ]);
            $ticket->messages()->create([
                'sender'    => SupportMessage::FROM_USER,
                'author_id' => $user->id,
                'body'      => trim($body),
            ]);

            return $ticket;
        });

        if ($screenshot) self::attach($ticket->messages()->first(), $user, $screenshot);

        return $ticket;
    }

    /** The user writes again: the ticket is waiting for support once more, even if it was closed. */
    public static function addUserMessage(SupportTicket $ticket, User $user, string $body, ?array $screenshot = null): SupportMessage
    {
        $message = DB::transaction(function () use ($ticket, $user, $body) {
            $message = $ticket->messages()->create([
                'sender'    => SupportMessage::FROM_USER,
                'author_id' => $user->id,
                'body'      => trim($body),
            ]);
            $ticket->forceFill([
                'status'          => SupportTicket::OPEN,
                'last_reply_from' => SupportMessage::FROM_USER,
                'closed_at'       => null,
                'unread_by_user'  => false,
            ])->touch();

            return $message;
        });

        if ($screenshot) self::attach($message, $user, $screenshot);

        return $message;
    }

    /**
     * Support answers: stored, emailed to the user and pushed to their
     * phones — both after the page has answered, so neither can hold it up.
     */
    public static function reply(SupportTicket $ticket, User $admin, string $body): SupportMessage
    {
        $message = DB::transaction(function () use ($ticket, $admin, $body) {
            $message = $ticket->messages()->create([
                'sender'    => SupportMessage::FROM_SUPPORT,
                'author_id' => $admin->id,
                'body'      => trim($body),
            ]);
            $ticket->forceFill([
                'status'          => SupportTicket::ANSWERED,
                'last_reply_from' => SupportMessage::FROM_SUPPORT,
                'closed_at'       => null,
                'unread_by_user'  => true,
            ])->touch();

            return $message;
        });

        $user = $ticket->user;
        if ($user) {
            $title = 'Support replied: ' . Str::limit($ticket->subject, 60);
            $preview = Str::limit(preg_replace('/\s+/', ' ', $message->body), 140);
            $data = ['type' => 'support', 'ticket_id' => (string) $ticket->id];
            SendPush::dispatch($user->id, $title, $preview, $data);
            // A reply to their own question: sent even with activity emails off.
            if ($user->email) {
                try {
                    $user->notify(new SupportReplied($ticket->id, $ticket->subject, $message->body));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $message;
    }

    public static function close(SupportTicket $ticket): void
    {
        $ticket->forceFill(['status' => SupportTicket::CLOSED, 'closed_at' => now()])->save();
    }

    /** Back to waiting on whoever is next: support, unless support wrote last. */
    public static function reopen(SupportTicket $ticket): void
    {
        $ticket->forceFill([
            'status'    => $ticket->last_reply_from === SupportMessage::FROM_SUPPORT ? SupportTicket::ANSWERED : SupportTicket::OPEN,
            'closed_at' => null,
        ])->save();
    }

    /** The user has seen the latest reply. */
    public static function markRead(SupportTicket $ticket): void
    {
        if ($ticket->unread_by_user) {
            // Not a change anyone made: keep updated_at as the last message's time.
            $ticket->timestamps = false;
            $ticket->forceFill(['unread_by_user' => false])->save();
            $ticket->timestamps = true;
        }
    }

    /** @param array{0: string, 1: string} $screenshot */
    private static function attach(SupportMessage $message, User $user, array $screenshot): void
    {
        [$bytes, $extension] = $screenshot;
        $path = "{$user->id}/{$message->ticket_id}-{$message->id}-" . Str::lower(Str::random(10)) . ".{$extension}";
        Storage::disk(self::DISK)->put($path, $bytes);
        $message->forceFill(['screenshot_path' => $path])->save();
    }

    /** The screenshot itself; 404 when the message has none (or its file is lost). */
    public static function screenshotResponse(SupportMessage $message): Response
    {
        $path = $message->screenshot_path;
        $disk = Storage::disk(self::DISK);
        if (! $path || ! $disk->exists($path)) abort(404, 'This message has no screenshot.');

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $disk->response($path, "screenshot-{$message->id}.{$extension}", [
            'Content-Type'           => self::MIME[$extension] ?? 'application/octet-stream',
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /** Every screenshot a user sent, once their account is gone (the rows go with the user). */
    public static function deleteAllFor(User $user): void
    {
        Storage::disk(self::DISK)->deleteDirectory((string) $user->id);
    }

    /** Ticket in the API. */
    public static function toApi(SupportTicket $ticket): array
    {
        return [
            'id'              => $ticket->id,
            'subject'         => $ticket->subject,
            'status'          => $ticket->status,
            'created_at'      => $ticket->created_at?->toIso8601String(),
            'updated_at'      => $ticket->updated_at?->toIso8601String(),
            'last_reply_from' => $ticket->last_reply_from,
            'unread'          => (bool) $ticket->unread_by_user,
        ];
    }

    /** TicketDetail in the API: the ticket and its conversation, oldest first. */
    public static function toApiDetail(SupportTicket $ticket): array
    {
        return self::toApi($ticket) + [
            'messages' => $ticket->messages->map(fn (SupportMessage $m) => [
                'id'             => $m->id,
                'from'           => $m->sender,
                'body'           => $m->body,
                'created_at'     => $m->created_at?->toIso8601String(),
                'has_screenshot' => $m->screenshot_path !== null,
            ])->values()->all(),
        ];
    }
}
