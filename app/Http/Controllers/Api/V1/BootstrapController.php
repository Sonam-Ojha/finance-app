<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ApiUser;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $categories = Category::where('user_id', $user->id)
            ->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => [
            'user'       => ApiUser::payload($user),
            'categories' => $categories,
            // The user's rates and added currencies, as GET /me/currencies.
            'currencies' => $user->userCurrencies()->orderBy('id')->get()->map->toApiShape(),
        ]]);
    }
}
