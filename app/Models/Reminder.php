<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'due_date', 'amount', 'notes', 'is_done'];
    protected $casts = ['due_date' => 'date', 'amount' => 'decimal:2', 'is_done' => 'boolean'];
    public function user() { return $this->belongsTo(User::class); }
}
