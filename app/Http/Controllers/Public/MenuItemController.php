<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::where('is_available', true);

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('description', 'like', '%'.$request->search.'%');
        }

        $menuItems = $query->orderBy('category')->orderBy('name')->get()->map(function ($item) {
            $item->is_out_of_stock = ($item->daily_stock !== null && $item->current_stock <= 0);
            return $item;
        });

        return Inertia::render('Public/Katalog', [
            'menuItems' => $menuItems,
            'filters' => $request->only(['category', 'search']),
        ]);
    }
}
