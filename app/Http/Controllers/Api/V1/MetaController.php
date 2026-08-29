<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\Category;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    public function index(Request $request)
    {
        $uid = $request->user()->id;
        return response()->json(['data' => [
            'expense_count'  => AppExpense::where('user_id', $uid)->count(),
            'category_count' => Category::where('user_id', $uid)->count(),
            'api_version'    => '1.0.0',
        ]]);
    }
}
