<?php

namespace Tests\Unit;

use App\Support\DisplayCurrency;
use PHPUnit\Framework\TestCase;

/** Pins the website's money text to what the app's src/lib/money.ts shows. */
class DisplayCurrencyTest extends TestCase
{
    private function inr(): DisplayCurrency
    {
        return new DisplayCurrency('INR', '₹', 2, 'Indian Rupee', 1.0);
    }

    public function test_rupees_use_lakh_grouping(): void
    {
        $this->assertSame('₹1,72,302.00', $this->inr()->format(17230200));
        $this->assertSame('₹999.50', $this->inr()->format(99950));
        $this->assertSame('-₹1,234.00', $this->inr()->format(-123400));
        $this->assertSame('₹0.00', $this->inr()->format(0));
    }

    public function test_other_currencies_convert_at_the_rate_and_group_by_threes(): void
    {
        $usd = new DisplayCurrency('USD', '$', 2, 'US Dollar', 80.0);
        $this->assertSame('$12,500.00', $usd->format(100000000));   // ₹10,00,000 at ₹80
        $this->assertSame(800000, $usd->parse('100'));              // typed $100 is stored as ₹8,000

        $yen = new DisplayCurrency('JPY', '¥', 0, 'Japanese Yen', 0.5);
        $this->assertSame('¥5,160', $yen->format(258000));          // ₹2,580 at ₹0.50
        $this->assertNull($yen->parse('10.5'));                     // yen has no decimals

        $unset = new DisplayCurrency('EUR', '€', 2, 'Euro', null);
        $this->assertSame('€1,000.00', $unset->format(100000));     // no rate: shown unconverted
    }

    public function test_compact_form(): void
    {
        $inr = $this->inr();
        $this->assertSame('₹12.4k', $inr->compact(1240000));
        $this->assertSame('₹1.2L', $inr->compact(12000000));
        $this->assertSame('₹3.1Cr', $inr->compact(3100000000));
        $this->assertSame('₹500', $inr->compact(50000));
        $this->assertSame('$1M', (new DisplayCurrency('USD', '$', 2, 'US Dollar', 1.0))->compact(100000000));
    }

    public function test_parse_follows_the_apps_rules(): void
    {
        $inr = $this->inr();
        $this->assertSame(123450, $inr->parse('1,234.50'));
        $this->assertSame(123450, $inr->parse('1234.5'));
        $this->assertSame(1234567800, $inr->parse('1,23,45,678'));
        $this->assertNull($inr->parse('1,5'));           // not read as 15
        $this->assertNull($inr->parse('0'));
        $this->assertNull($inr->parse('1.234'));         // too many decimals
        $this->assertNull($inr->parse('abc'));
        $this->assertNull($inr->parse('1234567890123')); // 13 whole digits
        $this->assertSame('1234.50', $inr->toInput(123450));
    }

    public function test_budget_usage_adds_up_on_screen(): void
    {
        $this->assertSame(
            ['over' => false, 'ratio' => 0.24, 'progress' => '₹1,200.00 of ₹5,000.00', 'remainder' => '₹3,800.00 left'],
            $this->inr()->budgetUsage(120000, 500000),
        );
        $this->assertSame('₹200.00 over', $this->inr()->budgetUsage(520000, 500000)['remainder']);
    }
}
