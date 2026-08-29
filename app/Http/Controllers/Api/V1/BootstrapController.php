<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $categories = Category::where('user_id', $user->id)
            ->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => [
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name ?? '',
                'email'      => $user->email,
                'onboarded'  => (bool) $user->onboarded,
                'has_pin'    => $user->has_pin,
                'currency'   => $user->currency,
                'theme_mode' => $user->theme_mode,
            ],
            'categories' => $categories,
        ]]);
    }
}
