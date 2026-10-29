<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A notification set to go out later: once, every day or every week, at a
 * time in India time (`app.display_timezone`). `next_run_at` is stored in
 * UTC like every other timestamp.
 */
class ScheduledNotification extends Model
{
    public const REPEATS = ['once' => 'Once', 'daily' => 'Every day', 'weekly' => 'Every week'];
    public const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    protected $fillable = [
        'created_by', 'title', 'body', 'audience', 'target_user_id', 'via_push', 'via_email',
        'repeat', 'time', 'weekday', 'next_run_at', 'last_run_at', 'runs', 'active',
    ];

    protected $casts = [
        'via_push'    => 'boolean',
        'via_email'   => 'boolean',
        'weekday'     => 'integer',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'runs'        => 'integer',
        'active'      => 'boolean',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function target() { return $this->belongsTo(User::class, 'target_user_id'); }

    /**
     * The first daily or weekly run after `$after`. A one-off has no next run:
     * its time is set when it is created.
     */
    public function nextRunAfter(CarbonInterface $after): ?Carbon
    {
        if ($this->repeat === 'once') return null;

        $zone = config('app.display_timezone');
        [$hour, $minute] = array_map('intval', explode(':', $this->time));
        $local = Carbon::instance($after)->setTimezone($zone);
        $next = $local->copy()->setTime($hour, $minute);

        if ($this->repeat === 'weekly') {
            $next->addDays(($this->weekday - $next->dayOfWeek + 7) % 7);
            if ($next->lte($local)) $next->addWeek();
        } elseif ($next->lte($local)) {
            $next->addDay();
        }

        return $next->utc();
    }

    /** "Every day at 7:00 PM", "Every Monday at 9:00 AM", "Once, Thu 30 Oct at 7:00 PM". */
    public function describe(): string
    {
        $at = Carbon::createFromFormat('H:i', $this->time)->format('g:i A');

        return match ($this->repeat) {
            'daily'  => "Every day at {$at}",
            'weekly' => 'Every ' . self::WEEKDAYS[$this->weekday] . " at {$at}",
            default  => 'Once, ' . ($this->next_run_at ?? $this->last_run_at)?->timezone(config('app.display_timezone'))->format('D j M') . " at {$at}",
        };
    }
}
