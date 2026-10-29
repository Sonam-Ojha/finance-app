<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BirthdayWish;
use App\Support\AppControl;
use App\Support\Labels;
use App\Support\UserPush;
use Illuminate\Console\Command;
use Throwable;

/**
 * "Happy birthday" by push and email, once a year, from 9 a.m. India time on
 * the user's birthday. A 29 February birthday is wished on the 28th in other
 * years. Admin › App control can switch it off.
 */
class SendBirthdayWishes extends Command
{
    protected $signature = 'birthdays:wish';

    protected $description = 'Wish users a happy birthday by push and email';

    public const FROM_HOUR = 9;

    public function handle(): int
    {
        if (! AppControl::birthdayWishes()) return self::SUCCESS;
        $now = Labels::now();
        if ($now->hour < self::FROM_HOUR) return self::SUCCESS;

        $leapDayToo = $now->month === 2 && $now->day === 28 && ! $now->isLeapYear();
        $wished = 0;

        User::query()
            ->whereNotNull('birthday')
            ->where(fn ($q) => $q->whereNull('birthday_wished_year')->orWhere('birthday_wished_year', '<', $now->year))
            ->where(function ($q) use ($now, $leapDayToo) {
                $q->where(fn ($d) => $d->whereMonth('birthday', $now->month)->whereDay('birthday', $now->day));
                if ($leapDayToo) $q->orWhere(fn ($d) => $d->whereMonth('birthday', 2)->whereDay('birthday', 29));
            })
            ->each(function (User $user) use ($now, &$wished) {
                $user->forceFill(['birthday_wished_year' => $now->year])->save();
                $first = strtok(trim($user->name ?? ''), ' ') ?: null;

                UserPush::send($user, 'Happy birthday' . ($first ? ", {$first}" : '') . '! 🎂',
                    'Wishing you a wonderful year ahead — from all of us at Expense Tracker.', ['type' => 'birthday']);
                if ($user->email && $user->email_notifications !== false) {
                    try {
                        $user->notify(new BirthdayWish());
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
                $wished++;
            });

        $this->info("Wished {$wished} happy birthday.");

        return self::SUCCESS;
    }
}
