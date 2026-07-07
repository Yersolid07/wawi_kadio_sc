<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Facility;
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
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%")))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->facility_id, fn($q) => $q->where('facility_id', $request->facility_id))
            ->when($request->date_from, fn($q) => $q->where('check_in_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('check_in_date', '<=', $request->date_to))
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

        $reservation->update(['status' => $validated['status']]);

        $reservation->user->notify(new ReservationStatusUpdated($reservation, $validated['status']));

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
