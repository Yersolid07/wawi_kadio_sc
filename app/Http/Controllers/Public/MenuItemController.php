<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Inertia\Inertia;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::where('is_available', true);

        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        $menuItems = $query->orderBy('category')->orderBy('name')->get();

        return Inertia::render('Public/Katalog', [
            'menuItems' => $menuItems,
            'filters' => $request->only(['category', 'search'])
        ]);
    }
}
