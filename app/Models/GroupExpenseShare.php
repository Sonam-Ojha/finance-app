<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What one person owes of a group expense. */
class GroupExpenseShare extends Model
{
    public $timestamps = false;

    protected $fillable = ['group_expense_id', 'user_id', 'amount_minor'];

    protected $casts = [
        'group_expense_id' => 'integer',
        'user_id'          => 'integer',
        'amount_minor'     => 'integer',
    ];

    public function expense() { return $this->belongsTo(GroupExpense::class, 'group_expense_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
