<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One message in a support ticket: from the user, or a reply from support. */
class SupportMessage extends Model
{
    public const FROM_USER = 'user';
    public const FROM_SUPPORT = 'support';

    protected $fillable = ['ticket_id', 'sender', 'author_id', 'body', 'screenshot_path'];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }

    public function author() { return $this->belongsTo(User::class, 'author_id'); }

    public function fromSupport(): bool
    {
        return $this->sender === self::FROM_SUPPORT;
    }
}
