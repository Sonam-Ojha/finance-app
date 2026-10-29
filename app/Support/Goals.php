<?php

namespace App\Support;

use App\Jobs\SendPush;
use App\Models\GoalContribution;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Savings goals, shared by the API and the website. The one place that
 * writes contributions: a goal's saved_minor is always the sum of them, and
 * a withdrawal can never take it below zero. The first time a goal reaches
 * its target, its owner gets a 🎉 push — once.
 */
class Goals
{
    /** The website's emoji picker. The API takes any short emoji. */
    public const EMOJIS = ['🎯', '✈️', '🏖️', '🏠', '🚗', '🏍️', '💍', '🎓', '💻', '📱', '🎁', '👶', '🩺', '🛡️', '💰', '🎉'];

    /** The category colour picker (CategoryPalette). */
    public const COLORS = ['#DA2020', '#EF661A', '#FCBD4C', '#34A866', '#228085', '#199CDA', '#283897', '#7E8ACC', '#915FC8', '#DA558C', '#B28B4E', '#737373'];

    public const NAME_MAX = 40;

    public const NOTE_MAX = 120;

    /** Rules for the fields the API and the website share. */
    public static function rules(?SavingsGoal $goal): array
    {
        return [
            'name'     => 'required|string|max:' . self::NAME_MAX,
            'emoji'    => 'required|string|max:16',
            'color'    => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Today or later — unless it is the deadline the goal already has.
            'deadline' => ['bail', 'nullable', 'date_format:Y-m-d', 'before:2100-01-01', function (string $attribute, $value, $fail) use ($goal) {
                if ($goal?->deadline?->toDateString() === $value) return;
                if ($value < Labels::now()->toDateString()) $fail('Pick a deadline that is today or later.');
            }],
        ];
    }

    public const MESSAGES = [
        'name.required'        => 'Give the goal a name.',
        'name.string'          => 'Give the goal a name.',
        'name.max'             => 'Keep the name to 40 characters.',
        'emoji.*'              => 'Pick an emoji.',
        'color.*'              => 'Pick a colour.',
        'deadline.date_format' => 'Pick a deadline like 2027-03-31, or none.',
        'deadline.before'      => 'Pick a deadline before 2100.',
    ];

    /** A user's goals: the active ones first (oldest first), then the archived (latest archived first). */
    public static function forUser(int $uid): Collection
    {
        return SavingsGoal::where('user_id', $uid)
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('archived_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Puts money in (positive) or takes it out (negative). A withdrawal of
     * more than the goal holds is refused with a message under $field.
     */
    public static function contribute(SavingsGoal $goal, int $amountMinor, ?string $note, string $on, string $field = 'amount_minor'): GoalContribution
    {
        $contribution = DB::transaction(function () use ($goal, $amountMinor, $note, $on, $field) {
            // One at a time per goal, so two withdrawals cannot both pass the check.
            $locked = SavingsGoal::whereKey($goal->id)->lockForUpdate()->firstOrFail();
            $saved  = (int) $locked->contributions()->sum('amount_minor');

            if ($saved + $amountMinor < 0) {
                $money = DisplayCurrency::for($goal->user);
                throw ValidationException::withMessages([$field => $saved > 0
                    ? 'You can withdraw at most ' . $money->format($saved) . ' — that is all this goal holds.'
                    : 'There is nothing saved in this goal to withdraw yet.']);
            }

            $contribution = $locked->contributions()->create([
                'amount_minor' => $amountMinor,
                'note'         => trim((string) $note) ?: null,
                'on_date'      => $on,
            ]);
            self::sync($locked);

            return $contribution;
        });

        $goal->refresh();
        self::celebrateIfReached($goal);

        return $contribution;
    }

    /**
     * Removes one of the goal's contributions (a 404 for one from another
     * goal). Refused, with a message under $field, when the goal would then
     * hold less than nothing — removing money put in before a withdrawal.
     */
    public static function deleteContribution(SavingsGoal $goal, int|string $contributionId, string $field = 'contribution'): GoalContribution
    {
        $contribution = DB::transaction(function () use ($goal, $contributionId, $field) {
            // One at a time per goal, like contribute().
            $locked = SavingsGoal::whereKey($goal->id)->lockForUpdate()->firstOrFail();
            $contribution = ctype_digit((string) $contributionId) ? $locked->contributions()->find((int) $contributionId) : null;
            if (! $contribution) abort(404, 'Not found.');

            $saved = (int) $locked->contributions()->sum('amount_minor');
            if ($saved - $contribution->amount_minor < 0) {
                $money = DisplayCurrency::for($goal->user);
                throw ValidationException::withMessages([$field => 'Removing this would leave the goal below ' . $money->format(0)
                    . ' — it holds ' . $money->format($saved) . '. Remove a withdrawal first.']);
            }

            $contribution->delete();
            self::sync($locked);

            return $contribution;
        });

        $goal->refresh();
        // Removing a withdrawal can bring a goal up to its target.
        self::celebrateIfReached($goal);

        return $contribution;
    }

    /** Sets saved_minor to the sum of the goal's contributions. */
    public static function sync(SavingsGoal $goal): void
    {
        $goal->forceFill(['saved_minor' => (int) $goal->contributions()->sum('amount_minor')])->save();
    }

    /**
     * The first time a goal reaches its target: marks it (reached_at) and
     * pushes 🎉 to its owner's phones. Never again for that goal, even if
     * money comes out and goes back in. Returns whether it celebrated now.
     */
    public static function celebrateIfReached(SavingsGoal $goal): bool
    {
        if (! $goal->isReached() || $goal->reached_at !== null) return false;

        // The update is the claim: of two requests racing here, one wins.
        $claimed = SavingsGoal::whereKey($goal->id)->whereNull('reached_at')->update(['reached_at' => now()]);
        if ($claimed !== 1) return false;
        $goal->refresh();

        $user  = $goal->user;
        $money = DisplayCurrency::for($user);
        $title = "🎉 Goal reached: {$goal->name}";
        $body  = 'You saved ' . $money->format($goal->saved_minor) . " for {$goal->emoji} {$goal->name}. Well done!";
        // From the queue, so a slow Firebase never delays the save.
        try {
            SendPush::dispatch($user->id, $title, $body, ['type' => 'goal_reached', 'goal_id' => (string) $goal->id]);
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }

    /** Creates a goal for the user from checked fields. */
    public static function create(User $user, array $fields): SavingsGoal
    {
        $goal = SavingsGoal::create(self::clean($fields) + ['user_id' => $user->id]);
        $goal->refresh();

        return $goal;
    }

    /** Changes a goal's fields; `archived` (bool) archives or brings it back. */
    public static function update(SavingsGoal $goal, array $fields, ?bool $archived = null): SavingsGoal
    {
        $changes = self::clean($fields);
        if ($archived !== null && $archived !== $goal->isArchived()) {
            $changes['archived_at'] = $archived ? now() : null;
        }
        $goal->update($changes);
        self::celebrateIfReached($goal);

        return $goal;
    }

    private static function clean(array $fields): array
    {
        return [
            'name'         => trim((string) $fields['name']),
            'emoji'        => trim((string) $fields['emoji']),
            'color'        => strtoupper((string) $fields['color']),
            'target_minor' => (int) $fields['target_minor'],
            'deadline'     => ($fields['deadline'] ?? null) ?: null,
        ];
    }
}
