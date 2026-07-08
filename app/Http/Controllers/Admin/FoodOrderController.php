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
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/FoodOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'type']),
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

        $order->update([
            'status' => $validated['status'],
            'payment_status' => $validated['payment_status'] ?? $order->payment_status,
        ]);

        $order->user->notify(new FoodOrderStatusUpdated($order, $validated['status']));

        return back()->with('success', "Status order diperbarui ke {$validated['status']}.");
    }
}
