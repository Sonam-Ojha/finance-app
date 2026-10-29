<?php

namespace App\Support;

use App\Models\User;

/**
 * The currency a user reads amounts in: the website's port of the app's
 * src/lib/money.ts, so the same stored integer reads the same in both.
 *
 * Amounts are always stored as hundredths of a rupee. A rate (rupees per one
 * unit) converts them for display and converts typed amounts back; with no
 * rate set they show unconverted. Floats never touch an amount — the rate is
 * the only one, and every conversion rounds straight back to hundredths.
 */
class DisplayCurrency
{
    private const STORAGE_DECIMALS = 2;
    private const STORAGE_FACTOR = 100;

    /** Longest whole part any amount field accepts. */
    public const MAX_INTEGER_DIGITS = 12;

    /** Thousands grouping in either convention; the last group must be three digits. */
    private const GROUPED_AMOUNT = '/^\d{1,3}(,\d{2,3})*,\d{3}(\.\d*)?$/';

    public function __construct(
        public readonly string $code,
        public readonly string $symbol,
        public readonly int $decimals,
        public readonly string $label,
        /** Rupees for one unit; null until the user sets one. */
        public readonly ?float $rate,
        /** Added by the user, so it can be edited and removed. */
        public readonly bool $custom = false,
    ) {}

    /** Every currency on offer (Admin › Currencies) first, then the user's own. */
    public static function allFor(User $user): array
    {
        $rows = $user->userCurrencies()->orderBy('id')->get()->keyBy('code');

        $list = [];
        foreach (Currencies::all() as $code => $meta) {
            $rate = $code === Currencies::BASE ? 1.0 : (isset($rows[$code]) ? (float) $rows[$code]->rate : null);
            $list[] = new self($code, $meta['symbol'], $meta['decimals'], $meta['label'], $rate);
        }
        foreach ($rows as $code => $row) {
            if (Currencies::isBuiltIn($code)) continue;
            // One an admin switched off keeps its look for whoever already set a rate for it.
            $known = Currencies::meta($code);
            $list[] = new self($code, $row->symbol ?: ($known['symbol'] ?? $code), $known['decimals'] ?? 2,
                $row->label ?: ($known['label'] ?? $code), (float) $row->rate, true);
        }

        return $list;
    }

    /** The one the user picked, or rupees. */
    public static function for(User $user): self
    {
        $all = self::allFor($user);
        foreach ($all as $currency) {
            if ($currency->code === $user->currency) return $currency;
        }

        return $all[0];
    }

    public function toDisplayMinor(int $stored): int
    {
        return $this->rate ? (int) round($stored / $this->rate) : $stored;
    }

    public function toStoredMinor(int $display): int
    {
        return $this->rate ? (int) round($display * $this->rate) : $display;
    }

    /** "₹1,23,456.00" — lakh grouping for rupees, threes for the rest. */
    public function format(int $stored): string
    {
        return $this->formatDisplayMinor($this->toDisplayMinor($stored));
    }

    /** Compact form for chart labels and tiles: ₹12.4k, ₹1.2L, ₹3.1Cr. */
    public function compact(int $stored): string
    {
        $display = $this->toDisplayMinor($stored);
        $major   = abs($display) / self::STORAGE_FACTOR;
        $sign    = $display < 0 ? '-' : '';
        $trim    = function (float $value): string {
            $fixed = number_format($value, 1, '.', '');
            return str_ends_with($fixed, '.0') ? substr($fixed, 0, -2) : $fixed;
        };

        $steps = $this->code === 'INR'
            ? [[1e7, 'Cr'], [1e5, 'L'], [1e3, 'k']]
            : [[1e9, 'B'], [1e6, 'M'], [1e3, 'k']];
        foreach ($steps as [$size, $suffix]) {
            if ($major >= $size) return $sign . $this->symbol . $trim($major / $size) . $suffix;
        }

        return $sign . $this->symbol . $trim($major);
    }

    /**
     * One budget's progress, worked out from the amounts as displayed so the
     * parts on screen add up.
     *
     * @return array{over: bool, ratio: float, progress: string, remainder: string}
     */
    public function budgetUsage(int $spentMinor, int $budgetMinor): array
    {
        $spent  = $this->displayAsShown($spentMinor);
        $budget = $this->displayAsShown($budgetMinor);
        $over   = $spent > $budget;

        return [
            'over'      => $over,
            'ratio'     => $budget > 0 ? $spent / $budget : 0.0,
            'progress'  => $this->formatDisplayMinor($spent) . ' of ' . $this->formatDisplayMinor($budget),
            'remainder' => $this->formatDisplayMinor(abs($budget - $spent)) . ($over ? ' over' : ' left'),
        ];
    }

    /**
     * A typed amount ("1,234.50") as stored hundredths of a rupee, or null
     * when it is not a usable amount: anything but digits, one point and
     * correctly placed separators ("1,5" is rejected), zero, more decimals
     * than the currency has, or too many whole digits.
     */
    public function parse(?string $input): ?int
    {
        $trimmed = preg_replace('/\s/', '', (string) $input);
        if (str_contains($trimmed, ',') && ! preg_match(self::GROUPED_AMOUNT, $trimmed)) return null;
        if (! preg_match('/^(\d*)(?:\.(\d*))?$/', str_replace(',', '', $trimmed), $m)) return null;

        $integer  = $m[1];
        $fraction = $m[2] ?? '';
        if ($integer === '' && $fraction === '') return null;
        if (strlen($integer) > self::MAX_INTEGER_DIGITS || strlen($fraction) > $this->decimals) return null;

        $display = (int) ($integer ?: '0') * self::STORAGE_FACTOR
            + (int) substr(str_pad($fraction, self::STORAGE_DECIMALS, '0'), 0, self::STORAGE_DECIMALS);
        $stored = $this->toStoredMinor($display);

        return $display > 0 && $stored > 0 ? $stored : null;
    }

    /** Stored hundredths as a plain editable string in this currency ("1234.50"). */
    public function toInput(int $stored): string
    {
        return number_format($this->toDisplayMinor($stored) / self::STORAGE_FACTOR, $this->decimals, '.', '');
    }

    /** What the browser needs to format chart labels the same way. */
    public function toJs(): array
    {
        return ['code' => $this->code, 'symbol' => $this->symbol, 'decimals' => $this->decimals, 'rate' => $this->rate];
    }

    private function displayAsShown(int $stored): int
    {
        $display = $this->toDisplayMinor($stored);
        $step    = 10 ** (self::STORAGE_DECIMALS - $this->decimals);
        $rounded = (int) round(abs($display) / $step) * $step;

        return $display < 0 ? -$rounded : $rounded;
    }

    private function formatDisplayMinor(int $display): string
    {
        $negative = $display < 0;
        // Rescale from hundredths to the currency's own precision; for yen
        // that rounds to whole units.
        $units    = (int) round(abs($display) / 10 ** (self::STORAGE_DECIMALS - $this->decimals));
        $factor   = 10 ** $this->decimals;
        $text     = $this->groupInteger((string) intdiv($units, $factor));
        if ($this->decimals > 0) {
            $text .= '.' . str_pad((string) ($units % $factor), $this->decimals, '0', STR_PAD_LEFT);
        }
        $text = $this->symbol . $text;

        return $negative ? "-{$text}" : $text;
    }

    private function groupInteger(string $digits): string
    {
        if ($this->code === 'INR' && strlen($digits) > 3) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3));
            return $rest . ',' . substr($digits, -3);
        }

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits);
    }
}
