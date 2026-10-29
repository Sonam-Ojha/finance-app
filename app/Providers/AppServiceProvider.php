<?php

namespace App\Providers;

use App\Support\Accounts;
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
        // What every signed-in page's frame shows: the currency the charts
        // format in, the bell's red dot, and the profile photo.
        View::composer('layouts.app', function ($view) {
            $user  = Auth::user();
            $money = DisplayCurrency::for($user);

            $view->with([
                'layoutMoney' => $money,
                'overBudget'  => Alerts::forMonth($user, Labels::now()->format('Y-m'), $money)['over'],
                'avatarUrl'   => Accounts::avatarUrl($user),
            ]);
        });
    }
}
