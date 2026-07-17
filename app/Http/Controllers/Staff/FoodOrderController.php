<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Notifications\FoodOrderStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MenuItem;
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
            'orders' => $orders,
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

        return back()->with('success', 'Status order diperbarui.');
    }

    public function updateTimer(Request $request, FoodOrder $order)
    {
        $validated = $request->validate([
            'estimated_ready_at' => 'required|date',
        ]);

        $order->update([
            'estimated_ready_at' => $validated['estimated_ready_at'],
        ]);

        return back()->with('success', 'Waktu estimasi diperbarui.');
    }

    public function destroy(FoodOrder $order)
    {
        DB::transaction(function () use ($order) {
            // Restore stock for items that were deducted
            foreach ($order->items as $item) {
                if ($item->menuItem && $item->menuItem->daily_stock !== null) {
                    $item->menuItem->increment('current_stock', $item->quantity);
                }
            }
            
            $order->delete();
        });

        return back()->with('success', 'Pesanan aktif berhasil dihapus dan stok telah dikembalikan.');
    }
}
