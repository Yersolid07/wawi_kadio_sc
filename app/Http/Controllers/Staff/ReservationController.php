<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Notifications\ReservationStatusUpdated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        return Inertia::render('Staff/Reservations/Index', [
            'checkIns' => Reservation::with(['user', 'facility'])
                ->where('check_in_date', $today)
                ->whereIn('status', ['confirmed'])
                ->get(),
            'checkOuts' => Reservation::with(['user', 'facility'])
                ->where('check_out_date', $today)
                ->whereIn('status', ['confirmed'])
                ->get(),
            'pending' => Reservation::with(['user', 'facility'])
                ->where('status', 'pending')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:confirmed,completed,cancelled',
        ]);

        $reservation->update(['status' => $validated['status']]);

        $reservation->user->notify(new ReservationStatusUpdated($reservation, $validated['status']));

        return back()->with('success', "Status berhasil diubah.");
    }
}
