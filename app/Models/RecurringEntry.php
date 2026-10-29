<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * An expense or income that repeats: every month on a day, every week on a
 * weekday, or every year on a date. `next_on` is the day it posts next;
 * `day` is the day of the month it was set for, so one set for the 31st
 * posts on the last day of shorter months and goes back to the 31st after.
 */
class RecurringEntry extends Model
{
    public const KINDS = ['expense', 'income'];
    public const FREQUENCIES = ['monthly' => 'Every month', 'weekly' => 'Every week', 'yearly' => 'Every year'];

    protected $fillable = [
        'user_id', 'kind', 'amount_minor', 'category_id', 'source', 'note', 'payment_method',
        'frequency', 'day', 'next_on', 'last_on', 'active',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'day'          => 'integer',
        'next_on'      => 'date',
        'last_on'      => 'date',
        'active'       => 'boolean',
    ];

    public function category() { return $this->belongsTo(Category::class); }
    public function user() { return $this->belongsTo(User::class); }

    /** Starting on `$first`: that day is its first post, and sets the day it repeats on. */
    public function startOn(CarbonInterface $first): static
    {
        $this->next_on = Carbon::instance($first)->startOfDay();
        $this->day = $this->next_on->day;

        return $this;
    }

    /** The occurrence after `$on`. */
    public function after(CarbonInterface $on): Carbon
    {
        $on = Carbon::instance($on)->startOfDay();

        return match ($this->frequency) {
            'weekly' => $on->copy()->addWeek(),
            'yearly' => $this->onDay($on->copy()->startOfMonth()->addYearNoOverflow()),
            default  => $this->onDay($on->copy()->startOfMonth()->addMonthNoOverflow()),
        };
    }

    private function onDay(Carbon $month): Carbon
    {
        return $month->setDay(min($this->day, $month->daysInMonth));
    }

    /** "Every month on the 31st", "Every week on Monday", "Every year on 12 March". */
    public function describe(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Every week on ' . $this->next_on->format('l'),
            'yearly' => 'Every year on ' . $this->day . ' ' . $this->next_on->format('F'),
            default  => 'Every month on the ' . $this->day . match (true) {
                in_array($this->day % 100, [11, 12, 13], true) => 'th',
                $this->day % 10 === 1 => 'st',
                $this->day % 10 === 2 => 'nd',
                $this->day % 10 === 3 => 'rd',
                default => 'th',
            },
        };
    }
}
