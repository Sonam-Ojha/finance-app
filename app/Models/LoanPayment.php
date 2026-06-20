<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    protected $fillable = ['user_id', 'loan_id', 'date', 'amount', 'note'];
    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function loan() { return $this->belongsTo(Loan::class); }
}
