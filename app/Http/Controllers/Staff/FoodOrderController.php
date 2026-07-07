<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Notifications\FoodOrderStatusUpdated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FoodOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Staff/FoodOrders/Index', [
            'activeOrders' => FoodOrder::with(['user', 'items.menuItem', 'reservation'])
                ->active()
                ->latest()
                ->get(),
        ]);
    }

    public function kds(): Response
    {
        $orders = FoodOrder::with(['items.menuItem', 'reservation.facility'])
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->latest()
            ->get();

        return Inertia::render('Staff/FoodOrders/KDS', [
            'orders' => $orders
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

        if ($order->user) {
            $order->user->notify(new FoodOrderStatusUpdated($order, $validated['status']));
        }

        return back()->with('success', "Status order diperbarui.");
    }

    public function updateTimer(Request $request, FoodOrder $order)
    {
        $validated = $request->validate([
            'estimated_ready_at' => 'required|date',
        ]);

        $order->update([
            'estimated_ready_at' => $validated['estimated_ready_at']
        ]);

        return back()->with('success', "Waktu estimasi diperbarui.");
    }
}
