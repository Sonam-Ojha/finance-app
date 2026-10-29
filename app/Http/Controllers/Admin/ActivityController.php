<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;

/** Admin › Activity log: everything admins did, newest first. */
class ActivityController extends Controller
{
    public function index()
    {
        return view('admin.activity.index', [
            'logs' => AdminActivityLog::with('admin:id,name,email')->latest('created_at')->latest('id')->paginate(25),
        ]);
    }
}
