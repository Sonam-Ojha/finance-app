<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Money put into a savings goal (positive) or taken out of it (negative).
 * Written only through App\Support\Goals, which keeps the goal's saved_minor
 * equal to their sum. `on_date` is "on" in the API.
 */
class GoalContribution extends Model
{
    protected $table = 'goal_contributions';

    protected $fillable = ['goal_id', 'amount_minor', 'note', 'on_date'];

    protected $casts = ['amount_minor' => 'integer', 'on_date' => 'date'];

    public function goal() { return $this->belongsTo(SavingsGoal::class, 'goal_id'); }

    /** The API's Contribution object. */
    public function toApiShape(): array
    {
        return [
            'id'           => $this->id,
            'amount_minor' => $this->amount_minor,
            'note'         => $this->note,
            'on'           => $this->on_date->toDateString(),
        ];
    }
}
