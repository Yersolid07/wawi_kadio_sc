<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NewReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $reservations = Reservation::with(['facility', 'payment', 'review'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return Inertia::render('Customer/Reservations/Index', [
            'reservations' => $reservations,
        ]);
    }

    public function create(Request $request): Response
    {
        $facilities = Facility::active()->get();

        return Inertia::render('Customer/Reservations/Create', [
            'facilities' => $facilities,
            'selectedFacilityId' => $request->facility_id,
            'initialCheckIn' => $request->check_in,
            'initialCheckOut' => $request->check_out,
            'initialCheckInTime' => $request->check_in_time,
            'initialCheckOutTime' => $request->check_out_time,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'guest_count' => 'required|integer|min:1',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $facility = Facility::findOrFail($validated['facility_id']);

        // Check availability
        if (!$facility->isAvailable($validated['check_in_date'], $validated['check_out_date'])) {
            return back()->withErrors(['check_in_date' => 'Fasilitas tidak tersedia untuk tanggal yang dipilih.']);
        }

        if ($facility->type === 'homestay' && $validated['check_in_date'] === $validated['check_out_date']) {
            return back()->withErrors(['check_out_date' => 'Untuk penginapan, tanggal check-out harus berbeda hari.']);
        }

        // Calculate total
        $checkIn = \Carbon\Carbon::parse($validated['check_in_date']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out_date']);
        $days = max($checkIn->diffInDays($checkOut), 1);

        if ($facility->type === 'homestay') {
            $total = $facility->price_per_day * $days;
        } else {
            // For gazebo/pool: price per hour, minimum 1 hour
            $checkInTime = \Carbon\Carbon::parse($validated['check_in_time'] ?? '08:00');
            $checkOutTime = \Carbon\Carbon::parse($validated['check_out_time'] ?? '17:00');
            $hours = max($checkInTime->diffInHours($checkOutTime), 1);
            $total = ($facility->price_per_hour ?? 0) * $hours * $days;
        }

        $reservation = Reservation::create([
            ...$validated,
            'user_id' => auth()->id(),
            'total_amount' => $total,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        // Notify Admins and Staff
        $adminsAndStaff = User::role(['admin', 'staff', 'manager'])->get();
        \Illuminate\Support\Facades\Notification::send($adminsAndStaff, new NewReservation($reservation));

        return redirect()->route('customer.reservations.coupon', $reservation)
            ->with('success', 'Reservasi berhasil dibuat. Silakan simpan kupon ini.');
    }

    public function coupon(Reservation $reservation): Response
    {
        Gate::authorize('view', $reservation);
        $reservation->load('facility');

        return Inertia::render('Customer/Reservations/Coupon', [
            'reservation' => $reservation,
        ]);
    }

    public function show(Reservation $reservation): Response
    {
        Gate::authorize('view', $reservation);

        $reservation->load(['facility', 'payment', 'foodOrders.items.menuItem', 'review']);

        return Inertia::render('Customer/Reservations/Show', [
            'reservation' => $reservation,
            'canReview' => $reservation->canBeReviewed(),
            'canCancel' => $reservation->canBeCancelled(),
        ]);
    }

    public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);

        if (!$reservation->canBeCancelled()) {
            return back()->with('error', 'Reservasi tidak dapat dibatalkan.');
        }

        $reservation->update(['status' => 'cancelled']);

        return back()->with('success', 'Reservasi berhasil dibatalkan.');
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
        ]);

        $facility = Facility::findOrFail($request->facility_id);
        $available = $facility->isAvailable($request->check_in_date, $request->check_out_date);

        return response()->json(['available' => $available]);
    }
}
