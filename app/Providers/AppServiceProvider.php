<?php

namespace App\Providers;

use App\Support\Alerts;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // What My account's frame (layouts/shell) needs on top of the admin
        // pages': the currency the charts format in, and the bell's red dot.
        View::composer('layouts.app', function ($view) {
            $user  = Auth::user();
            $money = DisplayCurrency::for($user);

            $view->with([
                'layoutMoney' => $money,
                'overBudget'  => Alerts::forMonth($user, Labels::now()->format('Y-m'), $money)['over'],
            ]);
        });
    }
}
