<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** The admin pages' frame: <x-admin-layout>. */
class AdminLayout extends Component
{
    public function render(): View
    {
        return view('layouts.admin');
    }
}
