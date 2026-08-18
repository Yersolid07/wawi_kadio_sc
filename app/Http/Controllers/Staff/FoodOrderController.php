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
    public function index(Request $request): Response
    {
        return Inertia::render('Staff/FoodOrders/Index', [
            'activeOrders' => FoodOrder::with(['items.menuItem', 'reservation.facility', 'user'])
                ->active()
                ->where(function ($query) {
                    $query->where('payment_status', 'paid')
                          ->orWhereHas('user', function ($q) {
                              $q->role(['staff', 'manager', 'admin']);
                          });
                })
                ->latest()
                ->get(),
        ]);
    }

    public function kds(): Response
    {
        $orders = FoodOrder::with([
            'items' => function ($query) {
                $query->whereHas('menuItem', function ($q) {
                    $q->whereIn('category', ['makanan', 'minuman', 'snack', 'dessert']);
                })->with('menuItem');
            },
            'reservation.facility',
            'user'
        ])
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                      ->orWhereHas('user', function ($q) {
                          $q->role(['staff', 'manager', 'admin']);
                      });
            })
            ->whereHas('items', function ($query) {
                $query->whereHas('menuItem', function ($q) {
                    $q->whereIn('category', ['makanan', 'minuman', 'snack', 'dessert']);
                });
            })
            ->oldest()
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

        DB::transaction(function () use ($order, $validated) {
            // If cancelling, restore daily stock for items that were deducted
            if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
                $order->load('items.menuItem');
                foreach ($order->items as $item) {
                    if ($item->menuItem && $item->menuItem->daily_stock !== null) {
                        $item->menuItem->increment('current_stock', $item->quantity);
                    }
                }
            }

            $order->update([
                'status'         => $validated['status'],
                'payment_status' => $validated['payment_status'] ?? $order->payment_status,
            ]);

            // If Paid and Cancelled, issue a refund transaction
            if ($validated['status'] === 'cancelled' && ($order->payment_status === 'paid' || $order->payment_status === 'refunded')) {
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
        });

        if ($order->user) {
            $order->fresh()->user?->notify(new FoodOrderStatusUpdated($order, $validated['status']));
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
        if ($order->status !== 'pending') {
            abort(403, 'Hanya pesanan pending yang dapat dihapus.');
        }

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
