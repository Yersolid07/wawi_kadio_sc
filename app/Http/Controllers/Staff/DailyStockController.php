<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DailyStockController extends Controller
{
    public function index()
    {
        // Load all available menu items for daily stock management
        $items = MenuItem::where('is_available', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
            
        return Inertia::render('Staff/DailyStock', [
            'items' => $items
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'stocks' => 'required|array',
            'stocks.*.id' => 'required|exists:menu_items,id',
            'stocks.*.current_stock' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['stocks'] as $stockData) {
                MenuItem::where('id', $stockData['id'])->update([
                    'current_stock' => $stockData['current_stock'],
                ]);
            }
        });

        return back()->with('success', 'Stok harian berhasil diperbarui.');
    }
}
