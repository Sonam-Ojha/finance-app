<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['user_id', 'expense_category_id', 'date', 'amount', 'payment_mode', 'description', 'receipt_photo'];
    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
}
