<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Reservation;
use App\Notifications\ReservationStatusUpdated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $reservations = Reservation::query()
            ->with(['user', 'facility'])
            ->when($request->search, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$request->search}%")))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->facility_id, fn ($q) => $q->where('facility_id', $request->facility_id))
            ->when($request->date_from, fn ($q) => $q->where('check_in_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->where('check_in_date', '<=', $request->date_to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Reservations/Index', [
            'reservations' => $reservations,
            'filters' => $request->only(['search', 'status', 'facility_id', 'date_from', 'date_to']),
            'facilities' => Facility::active()->get(['id', 'name']),
            'stats' => [
                'total' => Reservation::count(),
                'pending' => Reservation::where('status', 'pending')->count(),
                'confirmed' => Reservation::where('status', 'confirmed')->count(),
                'today' => Reservation::today()->count(),
            ],
        ]);
    }

    public function show(Reservation $reservation): Response
    {
        $reservation->load(['user', 'facility', 'payment', 'foodOrders.items.menuItem', 'review']);

        return Inertia::render('Admin/Reservations/Show', [
            'reservation' => $reservation,
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,completed',
            'note' => 'nullable|string|max:500',
        ]);

        // Business rules
        if ($validated['status'] === 'confirmed' && $reservation->status !== 'pending') {
            return back()->with('error', 'Hanya reservasi pending yang dapat dikonfirmasi.');
        }

        if ($validated['status'] === 'completed' && $reservation->status !== 'confirmed') {
            return back()->with('error', 'Hanya reservasi confirmed yang dapat di-complete.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($reservation, $validated) {
            $reservation->update(['status' => $validated['status']]);

            if ($validated['status'] === 'cancelled') {
                // If Reservation was paid, auto-refund in Ledger
                if ($reservation->payment_status === 'paid') {
                    $reservation->update(['payment_status' => 'refunded']);
                    
                    \App\Models\FinancialTransaction::create([
                        'type'             => 'expense',
                        'category'         => 'reservation',
                        'amount'           => $reservation->total_amount ?? 0, // Using total_amount from table
                        'description'      => "Refund Pembatalan Reservasi: {$reservation->id}",
                        'reference_id'     => $reservation->id,
                        'transaction_date' => now()->toDateString(),
                        'user_id'          => auth()->id(),
                    ]);
                }

                $reservation->load('foodOrders.items');
                foreach ($reservation->foodOrders as $foodOrder) {
                    if ($foodOrder->status !== 'cancelled') {
                        $foodOrder->update(['status' => 'cancelled']);
                        
                        // If associated FoodOrder was paid, auto-refund in Ledger too
                        if ($foodOrder->payment_status === 'paid') {
                            $foodOrder->update(['payment_status' => 'refunded']);
                            \App\Models\FinancialTransaction::create([
                                'type'             => 'expense',
                                'category'         => 'cafe',
                                'amount'           => $foodOrder->total_amount ?? 0,
                                'description'      => "Refund Pembatalan POS (via Reservasi): {$foodOrder->id}",
                                'reference_id'     => $foodOrder->id,
                                'transaction_date' => now()->toDateString(),
                                'user_id'          => auth()->id(),
                            ]);
                        }

                        foreach ($foodOrder->items as $item) {
                            if ($item->menu_item_id) {
                                \App\Models\MenuItem::where('id', $item->menu_item_id)
                                    ->whereNotNull('daily_stock')
                                    ->increment('current_stock', $item->quantity);
                            }
                        }
                    }
                }
            }
        });

        if ($reservation->user) {
            $reservation->user->notify(new ReservationStatusUpdated($reservation, $validated['status']));
        }

        return back()->with('success', "Status reservasi diubah ke {$validated['status']}.");
    }

    public function calendar(Request $request): Response
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $reservations = Reservation::query()
            ->with(['facility', 'user'])
            ->whereYear('check_in_date', $year)
            ->whereMonth('check_in_date', $month)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        return Inertia::render('Admin/Reservations/Calendar', [
            'reservations' => $reservations,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
