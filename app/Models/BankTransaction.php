<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $fillable = ['user_id', 'bank_account_id', 'type', 'date', 'amount', 'description'];
    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
}
