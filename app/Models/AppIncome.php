<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppIncome extends Model
{
    protected $table = 'app_incomes';

    protected $fillable = ['user_id', 'amount_minor', 'source', 'note', 'payment_method', 'received_at', 'recurring_entry_id'];

    protected $casts = ['amount_minor' => 'integer', 'received_at' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
}
