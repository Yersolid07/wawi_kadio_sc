<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MenuItemController extends Controller
{
    public function index(Request $request): Response
    {
        $items = MenuItem::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->status !== null, fn ($q) => $q->where('is_available', $request->status === 'available'))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/MenuItems/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'category', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/MenuItems/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:makanan,minuman,snack,dessert,tiket',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'is_available' => 'boolean',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'promo_start' => 'nullable|date',
            'promo_end' => 'nullable|date|after_or_equal:promo_start',
            'promo_name' => 'nullable|string|max:255',
            'daily_stock' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_url'] = $request->file('image')->store('menu', 'public');
        }

        if (isset($validated['daily_stock'])) {
            $validated['current_stock'] = $validated['daily_stock'];
        }

        MenuItem::create($validated);

        return redirect()->route('admin.menu-items.index')
            ->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit(MenuItem $menuItem): Response
    {
        return Inertia::render('Admin/MenuItems/Form', ['item' => $menuItem]);
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:makanan,minuman,snack,dessert,tiket',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'is_available' => 'boolean',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'promo_start' => 'nullable|date',
            'promo_end' => 'nullable|date|after_or_equal:promo_start',
            'promo_name' => 'nullable|string|max:255',
            'daily_stock' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            if ($menuItem->image_url) {
                Storage::disk('public')->delete($menuItem->image_url);
            }
            $validated['image_url'] = $request->file('image')->store('menu', 'public');
        }

        // If daily stock changed, adjust current stock relatively or reset it?
        // Let's reset it if admin changes daily_stock to a new valid number.
        if (array_key_exists('daily_stock', $validated) && $validated['daily_stock'] !== $menuItem->daily_stock) {
            $validated['current_stock'] = $validated['daily_stock'];
        }

        $menuItem->update($validated);

        return redirect()->route('admin.menu-items.index')
            ->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(MenuItem $menuItem)
    {
        if ($menuItem->image_url) {
            Storage::disk('public')->delete($menuItem->image_url);
        }
        $menuItem->delete();

        return redirect()->route('admin.menu-items.index')
            ->with('success', 'Menu berhasil dihapus.');
    }

    public function toggleAvailability(MenuItem $menuItem)
    {
        $menuItem->update(['is_available' => ! $menuItem->is_available]);

        return back()->with('success', 'Ketersediaan menu berhasil diubah.');
    }
}
