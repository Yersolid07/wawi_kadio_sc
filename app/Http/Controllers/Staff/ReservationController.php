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
            'status' => 'required|in:confirmed,completed,cancelled,checked_in',
        ]);

        $reservation->update(['status' => $validated['status']]);

        $reservation->user->notify(new ReservationStatusUpdated($reservation, $validated['status']));

        return back()->with('success', 'Status berhasil diubah.');
    }

    public function scan()
    {
        return Inertia::render('Staff/Reservations/Scan');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'unique_code' => 'required|string|exists:reservations,unique_code',
        ], [
            'unique_code.exists' => 'Kode reservasi tidak valid atau tidak ditemukan.',
        ]);

        $reservation = Reservation::with(['user', 'facility'])
            ->where('unique_code', $request->unique_code)
            ->firstOrFail();

        // Verify status and payment
        if ($reservation->payment_status !== 'paid') {
            return back()->withErrors(['unique_code' => 'Reservasi ini belum lunas. Status pembayaran: '.$reservation->payment_status]);
        }

        if (in_array($reservation->status, ['completed', 'cancelled'])) {
            return back()->withErrors(['unique_code' => 'Reservasi sudah selesai atau dibatalkan.']);
        }

        // Optional: Update status to checked_in automatically
        // $reservation->update(['status' => 'checked_in']);

        return back()->with('reservation', $reservation);
    }
}
