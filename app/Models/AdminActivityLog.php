<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing an admin did. Written through App\Support\AdminActivity::record(). */
class AdminActivityLog extends Model
{
    /** A log line is never edited. */
    public const UPDATED_AT = null;

    protected $fillable = ['admin_id', 'action', 'subject_type', 'subject_id', 'description', 'ip_address'];

    protected $casts = [
        'subject_id' => 'integer',
    ];

    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }
}
