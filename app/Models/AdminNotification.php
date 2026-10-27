<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A notification an admin sent to every user, or to one. */
class AdminNotification extends Model
{
    public const AUDIENCE_ALL  = 'all';
    public const AUDIENCE_USER = 'user';

    public const STATUS_SENT           = 'sent';
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    protected $fillable = [
        'sent_by', 'title', 'body', 'audience', 'target_user_id',
        'status', 'devices', 'delivered', 'failed',
    ];

    protected $casts = [
        'devices'   => 'integer',
        'delivered' => 'integer',
        'failed'    => 'integer',
    ];

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
