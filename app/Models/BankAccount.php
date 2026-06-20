<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = ['user_id', 'bank_name', 'account_number_last4', 'current_balance', 'account_type', 'ifsc_code', 'notes'];
    protected $casts = ['current_balance' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(BankTransaction::class); }
}
