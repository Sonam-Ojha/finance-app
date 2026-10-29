<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A notification an admin sent to every user, or to one, by push, email or both. */
class AdminNotification extends Model
{
    public const AUDIENCE_ALL  = 'all';
    public const AUDIENCE_USER = 'user';

    public const STATUS_SENT           = 'sent';
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    protected $fillable = [
        'sent_by', 'title', 'body', 'audience', 'target_user_id', 'via_push', 'via_email',
        'status', 'devices', 'delivered', 'failed', 'email_recipients', 'emails_sent', 'emails_failed',
    ];

    protected $casts = [
        'devices'   => 'integer',
        'delivered' => 'integer',
        'failed'    => 'integer',
        'via_push'  => 'boolean',
        'via_email' => 'boolean',
        'email_recipients' => 'integer',
        'emails_sent'      => 'integer',
        'emails_failed'    => 'integer',
    ];

    /** Emails go out after the page answers, so the counts can lag a moment behind. */
    public function emailsPending(): bool
    {
        return $this->via_email && $this->emails_sent + $this->emails_failed < $this->email_recipients;
    }

    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
    public function target() { return $this->belongsTo(User::class, 'target_user_id'); }

    /**
     * What one user's inbox shows: messages sent to them, and messages sent to
     * everyone since they joined — not ones from before their account existed.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('target_user_id', $user->id)
              ->orWhere(fn (Builder $all) => $all
                  ->where('audience', self::AUDIENCE_ALL)
                  ->where('created_at', '>=', $user->created_at));
        });
    }
}
