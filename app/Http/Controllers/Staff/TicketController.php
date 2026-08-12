<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index(): Response
    {
        $tickets = Facility::where('is_active', true)
            ->where(function($q) {
                $q->where('type', 'ticket')
                  ->orWhere('type', 'tiket')
                  ->orWhere('type', 'pool')
                  ->orWhere('type', 'gazebo')
                  ->orWhere('name', 'like', '%tiket%')
                  ->orWhere('name', 'like', '%ticket%');
            })
            ->get();

        $activeReservations = Reservation::with(['facility', 'user', 'payment'])
            ->whereDate('check_in_date', today())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Staff/Tickets/Index', [
            'tickets' => $tickets,
            'activeReservations' => $activeReservations,
            'user' => auth()->user(),
        ]);
    }

    public function store(Request $request, ReservationService $reservationService)
    {
        $validated = $request->validate([
            'facility_id'    => 'required|exists:facilities,id',
            'quantity'       => 'required|integer|min:1',
            'customer_name'  => 'nullable|string|max:255',
            'payment_method' => 'required|in:cash,qris,transfer',
        ]);

        DB::beginTransaction();
        try {
            $reservationData = [
                'facility_id'    => $validated['facility_id'],
                'check_in_date'  => date('Y-m-d'),
                'check_out_date' => date('Y-m-d'),
                'check_in_time'  => now()->format('H:i'),
                'check_out_time' => now()->addHours(4)->format('H:i'),
                'guest_count'    => $validated['quantity'],
                'customer_name'  => $validated['customer_name'] ?? 'Walk-in Ticket',
                'payment_method' => $validated['payment_method'],
            ];

            // Fix: pass all 3 required args (data, userId, sessionId)
            $reservation = $reservationService->createReservation($reservationData, auth()->id(), null);

            // Mark as confirmed
            $reservation->update(['status' => 'confirmed', 'payment_status' => 'paid']);

            // Create Payment record (the service doesn't auto-create one for POS tickets)
            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'amount'         => $reservation->total_amount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'success',
                'payment_date'   => now(),
            ]);

            // Record income in financial ledger
            \App\Services\PaymentService::recordIncome($payment);

            DB::commit();

            // Reload reservation with relations for printing
            $reservation->load(['facility', 'payment']);

            return back()->with('success', 'Tiket berhasil diterbitkan!')
                         ->with('print_ticket_id', $reservation->id)
                         ->with('order_details', $reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menerbitkan tiket: ' . $e->getMessage()]);
        }
    }
}
