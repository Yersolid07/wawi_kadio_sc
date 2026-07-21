<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NewReservation;
use App\Notifications\ReservationInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

        $paymentChannels = \Illuminate\Support\Facades\Cache::remember('tripay_channels', 86400, function () {
            try {
                return app(\App\Services\TripayService::class)->getPaymentChannels();
            } catch (\Exception $e) {
                return [];
            }
        });

        return Inertia::render('Customer/Reservations/Create', [
            'facilities' => $facilities,
            'paymentChannels' => $paymentChannels,
            'selectedFacilityId' => $request->facility_id,
            'initialCheckIn' => $request->check_in,
            'initialCheckOut' => $request->check_out,
            'initialCheckInTime' => $request->check_in_time,
            'initialCheckOutTime' => $request->check_out_time,
        ]);
    }

    public function store(\App\Http\Requests\StoreReservationRequest $request)
    {
        $validated = $request->validated();

        try {
            $reservation = app(\App\Services\ReservationService::class)
                ->createReservation(
                    $validated,
                    auth()->id(),
                    auth()->check() ? null : session()->getId()
                );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()])->withInput();
        }

        // Create Payment via PaymentService (handles Tripay integration if method=tripay)
        $paymentResult = app(\App\Services\PaymentService::class)
            ->createForReservation($reservation, $validated['payment_method'], $validated['payment_channel'] ?? null);
        $checkoutUrl = $paymentResult['checkout_url'];
        $tripayError = $paymentResult['error'] ?? null;

        // Notify Admins and Staff
        $adminsAndStaff = \App\Models\User::role(['admin', 'staff', 'manager'])->get();
        \Illuminate\Support\Facades\Notification::send($adminsAndStaff, new \App\Notifications\NewReservation($reservation));

        // Notify Customer (Invoice) if user is authenticated
        if (auth()->check()) {
            auth()->user()->notify(new \App\Notifications\ReservationInvoiceNotification($reservation));
        }

        if ($checkoutUrl) {
            return Inertia::location($checkoutUrl);
        }

        if ($validated['payment_method'] === 'tripay') {
            $msg = 'Gagal memproses pembayaran online' . ($tripayError ? ': ' . $tripayError : '') . '. Silakan bayar di kasir.';
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('error', $msg);
        }

        if ($validated['payment_method'] === 'cash') {
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('success', 'Reservasi berhasil. Silakan bayar di kasir Wawi Kadio saat kedatangan.');
        }

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservasi berhasil dibuat. Menunggu pembayaran.');
    }

    public function coupon(Reservation $reservation): Response
    {
        $this->authorize('view', $reservation);

        $reservation->load('facility');

        return Inertia::render('Customer/Reservations/Coupon', [
            'reservation' => $reservation,
            'qrCode' => QRCode::where('type', 'whatsapp')->first(),
        ]);
    }

    public function show(Reservation $reservation): Response
    {
        $this->authorize('view', $reservation);

        $reservation->load(['facility', 'payment', 'foodOrders.items.menuItem', 'review']);

        return Inertia::render('Customer/Reservations/Show', [
            'reservation' => $reservation,
            'canReview' => $reservation->canBeReviewed(),
            'canCancel' => $reservation->canBeCancelled(),
            'isGuest' => ! $reservation->user_id,
        ]);
    }

    public function cancel(Reservation $reservation)
    {
        $this->authorize('update', $reservation);

        if (! auth()->check()) {
            // Guests cannot cancel online — they must contact staff
            abort(403, 'Silakan hubungi staff untuk membatalkan reservasi.');
        }

        if (! $reservation->canBeCancelled()) {
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
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
        ]);

        $facility = Facility::findOrFail($request->facility_id);
        $available = $facility->isAvailable($request->check_in_date, $request->check_out_date, $request->check_in_time, $request->check_out_time);

        return response()->json(['available' => $available]);
    }

    public function checkCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string',
            'facility_id' => 'required|exists:facilities,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
        ]);

        $facility = Facility::findOrFail($request->facility_id);
        
        $checkIn = Carbon::parse($request->check_in_date);
        $checkOut = Carbon::parse($request->check_out_date);
        $days = max($checkIn->diffInDays($checkOut), 1);

        if ($facility->type === 'homestay' || $facility->type === 'gazebo') {
            $total = $facility->price_per_day * $days;
        } else {
            $checkInTime = Carbon::parse($request->check_in_time ?? '08:00');
            $checkOutTime = Carbon::parse($request->check_out_time ?? '17:00');
            $hours = max($checkInTime->diffInHours($checkOutTime), 1);
            $total = ($facility->price_per_hour ?? 0) * $hours * $days;
        }

        $coupon = Coupon::where('code', $request->coupon_code)->where('is_active', true)->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Kupon tidak ditemukan atau tidak aktif.']);
        }

        if (!$coupon->isValid($total)) {
            return response()->json(['valid' => false, 'message' => 'Kupon tidak valid atau syarat minimal belanja belum terpenuhi.']);
        }

        $discountAmount = $coupon->calculateDiscount($total);

        return response()->json([
            'valid' => true,
            'original_total' => $total,
            'discount_amount' => $discountAmount,
            'final_total' => $total - $discountAmount,
            'message' => 'Kupon berhasil diterapkan!'
        ]);
    }

}
