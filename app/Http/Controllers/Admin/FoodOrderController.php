<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Notifications\FoodOrderStatusUpdated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FoodOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = FoodOrder::with(['user', 'items.menuItem', 'reservation'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('order_type', $request->type))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/FoodOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'type', 'date_from', 'date_to']),
            'stats' => [
                'pending' => FoodOrder::where('status', 'pending')->count(),
                'preparing' => FoodOrder::where('status', 'preparing')->count(),
                'ready' => FoodOrder::where('status', 'ready')->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, FoodOrder $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:preparing,ready,delivered,cancelled',
            'payment_status' => 'nullable|in:unpaid,paid',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $validated) {
            $order->update([
                'status' => $validated['status'],
                'payment_status' => $validated['payment_status'] ?? $order->payment_status,
            ]);

            if ($validated['status'] === 'cancelled') {
                // Restore stock if it was cancelled
                $order->load('items.menuItem');
                foreach ($order->items as $item) {
                    if ($item->menuItem && $item->menuItem->daily_stock !== null) {
                        $item->menuItem->increment('current_stock', $item->quantity);
                    }
                }

                // If Paid, issue a refund transaction
                if ($order->payment_status === 'paid' || $order->payment_status === 'refunded') {
                    // Update to refunded if not already
                    if ($order->payment_status !== 'refunded') {
                        $order->update(['payment_status' => 'refunded']);
                    }
                    
                    \App\Models\FinancialTransaction::create([
                        'type'             => 'expense',
                        'category'         => 'cafe',
                        'amount'           => $order->total_amount ?? 0,
                        'description'      => "Refund Pembatalan POS: {$order->id}",
                        'reference_id'     => $order->id,
                        'transaction_date' => now()->toDateString(),
                        'user_id'          => auth()->id(),
                    ]);
                }
            }
        });

        if ($order->user) {
            $order->user->notify(new FoodOrderStatusUpdated($order, $validated['status']));
        }

        return back()->with('success', "Status order diperbarui ke {$validated['status']}.");
    }
}
