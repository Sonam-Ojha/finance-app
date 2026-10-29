<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppExpense extends Model
{
    protected $table = 'app_expenses';

    protected $fillable = ['user_id', 'amount_minor', 'category_id', 'note', 'payment_method', 'spent_at', 'recurring_entry_id'];

    protected $casts = ['amount_minor' => 'integer', 'spent_at' => 'date'];

    protected static function booted(): void
    {
        // A deleted expense takes its receipt photo with it (App\Support\Receipts).
        static::deleted(fn (AppExpense $expense) => \App\Support\Receipts::deleteFile($expense->receipt_path));
    }

    /** True when a receipt photo is attached (receipt_path, set only by App\Support\Receipts). */
    public function getHasReceiptAttribute(): bool
    {
        return $this->receipt_path !== null;
    }

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
}
