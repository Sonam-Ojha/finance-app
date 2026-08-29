<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const VALID_CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'AED', 'SGD'];

    public function updateCurrency(Request $request)
    {
        $request->validate(['currency' => 'required|in:' . implode(',', self::VALID_CURRENCIES)]);
        $request->user()->update(['currency' => $request->currency]);
        return response()->noContent();
    }

    public function updateTheme(Request $request)
    {
        $request->validate(['theme_mode' => 'required|in:light,dark,system']);
        $request->user()->update(['theme_mode' => $request->theme_mode]);
        return response()->noContent();
    }

    public function currencies()
    {
        return response()->json(['data' => [
            ['code' => 'INR', 'symbol' => '₹',   'decimals' => 2, 'label' => 'Indian Rupee'],
            ['code' => 'USD', 'symbol' => '$',   'decimals' => 2, 'label' => 'US Dollar'],
            ['code' => 'EUR', 'symbol' => '€',   'decimals' => 2, 'label' => 'Euro'],
            ['code' => 'GBP', 'symbol' => '£',   'decimals' => 2, 'label' => 'British Pound'],
            ['code' => 'JPY', 'symbol' => '¥',   'decimals' => 0, 'label' => 'Japanese Yen'],
            ['code' => 'AUD', 'symbol' => 'A$',  'decimals' => 2, 'label' => 'Australian Dollar'],
            ['code' => 'CAD', 'symbol' => 'C$',  'decimals' => 2, 'label' => 'Canadian Dollar'],
            ['code' => 'AED', 'symbol' => 'AED', 'decimals' => 2, 'label' => 'UAE Dirham'],
            ['code' => 'SGD', 'symbol' => 'S$',  'decimals' => 2, 'label' => 'Singapore Dollar'],
        ]]);
    }
}
