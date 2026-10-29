<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** A user's help request and its conversation. Written through App\Support\SupportTickets. */
class SupportTicket extends Model
{
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';

    public const STATUSES = [self::OPEN => 'Open', self::ANSWERED => 'Answered', self::CLOSED => 'Closed'];

    protected $fillable = ['user_id', 'subject', 'status', 'last_reply_from', 'unread_by_user', 'closed_at'];

    protected $casts = ['unread_by_user' => 'boolean', 'closed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public function messages() { return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('id'); }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }

    /** Waiting for support: the admin menu's badge. 0 until the table exists, so no page breaks before `migrate`. */
    public static function waitingCount(): int
    {
        try {
            return static::where('status', self::OPEN)->count();
        } catch (QueryException) {
            return 0;
        }
    }

    /** Replies a user has not opened yet: Settings' "new reply" badge. */
    public static function unreadRepliesFor(int $userId): int
    {
        try {
            return static::where('user_id', $userId)->where('unread_by_user', true)->count();
        } catch (QueryException) {
            return 0;
        }
    }
}
