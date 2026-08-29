<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppExpense extends Model
{
    protected $table = 'app_expenses';

    protected $fillable = ['user_id', 'amount_minor', 'category_id', 'note', 'spent_at'];

    protected $casts = ['amount_minor' => 'integer', 'spent_at' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
}
