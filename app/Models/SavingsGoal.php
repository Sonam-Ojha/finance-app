<?php

namespace App\Models;

use App\Support\Labels;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Money set aside for something ("Goa trip ₹20,000"). saved_minor is the sum
 * of its contributions, kept in step by App\Support\Goals — never set it
 * directly.
 */
class SavingsGoal extends Model
{
    protected $table = 'savings_goals';

    protected $fillable = ['user_id', 'name', 'emoji', 'color', 'target_minor', 'deadline', 'archived_at'];

    protected $casts = [
        'target_minor' => 'integer',
        'saved_minor'  => 'integer',
        'deadline'     => 'date',
        'reached_at'   => 'datetime',
        'archived_at'  => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function contributions() { return $this->hasMany(GoalContribution::class, 'goal_id'); }

    public function isReached(): bool
    {
        return $this->saved_minor >= $this->target_minor;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** 0..1, capped at 1 once the target is reached. */
    public function progress(): float
    {
        return $this->target_minor > 0 ? round(min(1, $this->saved_minor / $this->target_minor), 4) : 1.0;
    }

    public function remainingMinor(): int
    {
        return max(0, $this->target_minor - $this->saved_minor);
    }

    /**
     * Whole months left before the deadline, at least 1 (so a deadline this
     * month, or one already past, asks for everything left now). Null with
     * no deadline.
     */
    public function monthsLeft(): ?int
    {
        if (! $this->deadline) return null;

        $today = Labels::now()->startOfDay();
        $until = Carbon::parse($this->deadline->toDateString(), $today->getTimezone());
        $months = ($until->year - $today->year) * 12 + ($until->month - $today->month) - ($until->day < $today->day ? 1 : 0);

        return max(1, $months);
    }

    /** What to put away each month to make the deadline; null with no deadline or once reached. */
    public function monthlyNeededMinor(): ?int
    {
        $months = $this->monthsLeft();
        if ($months === null || $this->isReached()) return null;

        return (int) ceil($this->remainingMinor() / $months);
    }

    /** True once the deadline day has gone by. */
    public function isOverdue(): bool
    {
        return $this->deadline !== null && $this->deadline->toDateString() < Labels::now()->toDateString();
    }

    /** The API's Goal object. */
    public function toApiShape(): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'emoji'                => $this->emoji,
            'color'                => $this->color,
            'target_minor'         => $this->target_minor,
            'saved_minor'          => $this->saved_minor,
            'progress'             => $this->progress(),
            'reached'              => $this->isReached(),
            'deadline'             => $this->deadline?->toDateString(),
            'monthly_needed_minor' => $this->monthlyNeededMinor(),
            'archived'             => $this->isArchived(),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
