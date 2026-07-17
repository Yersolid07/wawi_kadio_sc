<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Models\User;
use App\Notifications\NewFoodOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Staff/POS/Index', [
            'menuItems' => MenuItem::where('is_available', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('category'),
            'activeOrders' => FoodOrder::with(['items.menuItem', 'reservation.facility'])
                ->whereIn('payment_status', ['unpaid', 'failed'])
                ->latest()
                ->get(),
            'user' => auth()->user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|exists:food_orders,id',
            'order_type' => 'required|in:dine_in,takeaway',
            'table_number' => 'required_if:order_type,dine_in|nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.notes' => 'nullable|string',
            'items.*.is_existing' => 'nullable|boolean',
            'payment_method' => 'required|in:cash,qris',
            'amount_paid' => 'required_if:payment_method,cash|numeric|min:0',
        ]);

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::findOrFail($item['menu_item_id']);
            $totalAmount += $menuItem->final_price * $item['quantity'];
        }

        if ($validated['payment_method'] === 'cash' && $validated['amount_paid'] < $totalAmount) {
            return back()->withErrors(['amount_paid' => 'Jumlah bayar kurang dari total tagihan.']);
        }

        try {
            $order = app(\App\Services\OrderService::class)->processPosOrder($validated, auth()->id());
        } catch (\Exception $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with([
            'success' => 'Transaksi berhasil diproses.',
            'print_order_id' => $order->id,
            'change_amount' => $validated['payment_method'] === 'cash' ? ($validated['amount_paid'] - $totalAmount) : 0,
        ]);
    }

    public function print(FoodOrder $order)
    {
        $order->load(['items.menuItem', 'user']);

        return view('reports.pos-receipt', compact('order'));
    }
}
