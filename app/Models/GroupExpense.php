<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Something one member paid for, shared between members. Its shares always
 * add up to amount_minor exactly.
 */
class GroupExpense extends Model
{
    public const SPLITS = ['equal', 'exact'];

    protected $fillable = ['group_id', 'description', 'amount_minor', 'paid_by', 'spent_on', 'split', 'created_by'];

    protected $casts = [
        'group_id'     => 'integer',
        'amount_minor' => 'integer',
        'paid_by'      => 'integer',
        'created_by'   => 'integer',
        'spent_on'     => 'date',
    ];

    public function group() { return $this->belongsTo(Group::class); }
    public function shares() { return $this->hasMany(GroupExpenseShare::class); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }

    public function shareOf(int $userId): int
    {
        return (int) ($this->shares->firstWhere('user_id', $userId)?->amount_minor ?? 0);
    }
}
