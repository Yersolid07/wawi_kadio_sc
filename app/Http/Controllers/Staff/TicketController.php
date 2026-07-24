<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Facility;
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
        // Only load facilities of type 'tiket' or similar
        // Adjust the type based on how it's actually classified in the database
        $tickets = Facility::where('is_active', true)
            ->where(function($q) {
                $q->where('type', 'ticket')
                  ->orWhere('type', 'tiket')
                  ->orWhere('name', 'like', '%tiket%')
                  ->orWhere('name', 'like', '%ticket%');
            })
            ->get();

        $activeReservations = Reservation::with(['facility', 'user', 'payment'])
            ->whereDate('check_in', today())
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
            // For POS Tickets, we generate a reservation that acts as a ticket
            // We set it for today and automatically confirm and pay it.
            $reservationData = [
                'facility_id'    => $validated['facility_id'],
                'check_in_date'  => date('Y-m-d'),
                'check_out_date' => date('Y-m-d'),
                'guest_count'    => $validated['quantity'],
                'customer_name'  => $validated['customer_name'] ?? 'Walk-in Ticket',
                'payment_method' => $validated['payment_method'],
            ];

            // Use the standard service, but override statuses
            $reservation = $reservationService->createReservation($reservationData, auth()->id());
            
            // Mark as confirmed and paid
            $reservation->update(['status' => 'confirmed']);
            
            if ($reservation->payment) {
                $reservation->payment->update([
                    'payment_status' => 'success',
                    'payment_date'   => now(),
                ]);
                \App\Services\PaymentService::recordIncome($reservation->payment);
            }

            DB::commit();

            return back()->with('success', 'Tiket berhasil diterbitkan!')->with('print_ticket_id', $reservation->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menerbitkan tiket: ' . $e->getMessage()]);
        }
    }
}
