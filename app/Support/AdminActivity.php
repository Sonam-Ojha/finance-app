<?php

namespace App\Support;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;

/** Writes the admin activity log. */
class AdminActivity
{
    /**
     * @param string $action      what happened, as "thing.verb": user.role_changed
     * @param string $description read after the admin's name: "made user #4821 an admin".
     *                            Refer to users by id, never by amount or anything they recorded.
     */
    public static function record(string $action, string $description, ?Model $subject = null): AdminActivityLog
    {
        return AdminActivityLog::create([
            'admin_id'     => auth()->id(),
            'action'       => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'description'  => mb_substr($description, 0, 255),
            'ip_address'   => request()->ip(),
        ]);
    }
}
