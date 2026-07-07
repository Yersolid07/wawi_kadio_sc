<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Notifications\NewFoodOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

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
            'guest_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
            'items.*.is_existing' => 'nullable|boolean',
            'payment_method' => 'required|in:cash,qris',
            'amount_paid' => 'required_if:payment_method,cash|numeric|min:0',
        ]);

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::findOrFail($item['menu_item_id']);
            $totalAmount += $menuItem->price * $item['quantity'];
        }

        if ($validated['payment_method'] === 'cash' && $validated['amount_paid'] < $totalAmount) {
            return back()->withErrors(['amount_paid' => 'Jumlah bayar kurang dari total tagihan.']);
        }

        if (!empty($validated['order_id'])) {
            $order = FoodOrder::findOrFail($validated['order_id']);
            
            // Check if there are new items to add to this order
            $newItemsAdded = false;
            foreach ($validated['items'] as $item) {
                if (empty($item['is_existing'])) {
                    $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                    $order->items()->create([
                        'menu_item_id' => $menuItem->id,
                        'quantity' => $item['quantity'],
                        'price' => $menuItem->price,
                        'notes' => $item['notes'] ?? null,
                    ]);
                    $newItemsAdded = true;
                }
            }

            // Always recalculate based on DB state to be safe, or just trust the new sum
            $order->recalculateTotal();
            
            $order->update([
                'payment_status' => 'paid',
                'user_id' => auth()->id(), // the cashier who closed this
            ]);

            // If there are new items, notify kitchen again
            if ($newItemsAdded) {
                $hasFood = false;
                foreach ($order->items as $orderItem) {
                    if ($orderItem->menuItem && $orderItem->menuItem->category !== 'tiket') {
                        $hasFood = true;
                        break;
                    }
                }

                if ($hasFood) {
                    $kitchenStaff = User::role(['staff', 'manager', 'admin'])->get();
                    Notification::send($kitchenStaff, new NewFoodOrder($order));
                }
            }
            
        } else {
            $orderData = [
                'total_amount' => $totalAmount,
                'order_type' => $validated['order_type'],
                'table_number' => $validated['table_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'guest_name' => $validated['guest_name'] ?? 'Guest Walk-in',
                'payment_status' => 'paid',
                'status' => 'pending', // Send to kitchen immediately
                'user_id' => auth()->id(),
            ];

            $order = FoodOrder::create($orderData);

            foreach ($validated['items'] as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->price,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Notify Kitchen Staff
            $hasFood = false;
            foreach ($order->items as $orderItem) {
                if ($orderItem->menuItem && $orderItem->menuItem->category !== 'tiket') {
                    $hasFood = true;
                    break;
                }
            }

            if ($hasFood) {
                $kitchenStaff = User::role(['staff', 'manager', 'admin'])->get();
                Notification::send($kitchenStaff, new NewFoodOrder($order));
            } else {
                $order->update(['status' => 'completed']); // auto complete if only tickets
            }
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
