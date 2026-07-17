<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(): Response
    {
        $reservationIds = Reservation::where('user_id', auth()->id())->pluck('id');

        return Inertia::render('Customer/Payments/Index', [
            'payments' => Payment::with(['reservation.facility'])
                ->whereIn('reservation_id', $reservationIds)
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'payment_method' => 'required|in:transfer,ewallet,tripay,midtrans,cash',
            'proof_image' => 'nullable|image|max:3072',
        ]);

        $reservation = Reservation::findOrFail($validated['reservation_id']);

        if (auth()->check()) {
            if ($reservation->user_id !== auth()->id() && !auth()->user()->hasRole(['admin', 'manager', 'staff'])) {
                abort(403, 'Akses ditolak.');
            }
        } else {
            if ($reservation->user_id !== null || $reservation->session_id !== session()->getId()) {
                abort(403, 'Sesi Anda tidak cocok dengan reservasi ini.');
            }
        }
        
        if ($reservation->payment_status === 'paid') {
            return back()->with('error', 'Reservasi ini sudah dibayar.');
        }

        $paymentData = [
            'reservation_id' => $validated['reservation_id'],
            'amount' => $reservation->total_amount,
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'pending',
        ];

        if ($request->hasFile('proof_image')) {
            $paymentData['proof_image'] = $request->file('proof_image')->store('payments/proofs', 'public');
        }

        // Handle Tripay (Default Online Payment)
        if ($validated['payment_method'] === 'tripay' || $validated['payment_method'] === 'midtrans' || $validated['payment_method'] === 'transfer' || $validated['payment_method'] === 'ewallet') {
            try {
                $apiKey = config('services.tripay.api_key');
                $privateKey = config('services.tripay.private_key');
                $merchantCode = config('services.tripay.merchant_code');
                $merchantRef = 'WAWI-'.time().'-'.$reservation->id;

                $data = [
                    'method' => 'QRIS', // Default to QRIS for simple testing, user can change if they have other channels
                    'merchant_ref' => $merchantRef,
                    'amount' => (int) $reservation->total_amount,
                    'customer_name' => $reservation->customer_name ?? (auth()->check() ? auth()->user()->name : 'Guest'),
                    'customer_email' => $reservation->customer_email ?? (auth()->check() ? auth()->user()->email : 'guest@example.com'),
                    'customer_phone' => $reservation->customer_phone ?? (auth()->check() ? (auth()->user()->phone ?? '-') : '-'),
                    'order_items' => [
                        [
                            'sku' => 'RES-'.$reservation->unique_code,
                            'name' => 'Reservasi '.$reservation->facility->name,
                            'price' => (int) $reservation->total_amount,
                            'quantity' => 1,
                        ],
                    ],
                    'return_url' => route('customer.reservations.show', $reservation->id),
                    'expired_time' => (time() + (24 * 60 * 60)), // 24 hours
                    'signature' => hash_hmac('sha256', $merchantCode.$merchantRef.(int) $reservation->total_amount, $privateKey),
                ];

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_URL => config('services.tripay.api_url', 'https://tripay.co.id/api-sandbox/transaction/create'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$apiKey],
                    CURLOPT_FAILONERROR => false,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($data),
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]);

                $response = curl_exec($curl);
                $error = curl_error($curl);
                curl_close($curl);

                if (empty($error)) {
                    $res = json_decode($response, true);
                    if ($res && isset($res['success']) && $res['success'] === true) {
                        $paymentData['payment_method'] = 'tripay';
                        $paymentData['transaction_id'] = $res['data']['reference'];
                        $paymentData['gateway_response'] = json_encode($res['data']);
                        $paymentData['payment_reference'] = $merchantRef;

                        $payment = DB::transaction(function () use ($paymentData) {
                            return Payment::create($paymentData);
                        });

                        return Inertia::location($res['data']['checkout_url']);
                    } else {
                        \Illuminate\Support\Facades\Log::error('Tripay API Error: ' . $response);
                        $paymentData['gateway_response'] = $response;
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Tripay API Exception: ' . $e->getMessage());
            }
        }

        // Fallback or non-tripay creation
        DB::transaction(function () use ($paymentData) {
            Payment::create($paymentData);
        });

        if ($validated['payment_method'] === 'cash') {
            return back()->with('success', 'Kami menunggu kedatangan Anda. Silakan bayar di kasir saat kedatangan.');
        }

        return back()->with('success', 'Pembayaran berhasil dicatat. Menunggu verifikasi.');
    }

    public function show(Payment $payment): Response
    {
        $reservation = $payment->reservation;
        if (auth()->check()) {
            if ($reservation && $reservation->user_id !== auth()->id() && !auth()->user()->hasRole(['admin', 'manager', 'staff'])) {
                abort(403, 'Akses ditolak.');
            }
        } else {
            if ($reservation && ($reservation->user_id !== null || $reservation->session_id !== session()->getId())) {
                abort(403, 'Sesi Anda tidak cocok dengan pembayaran ini.');
            }
        }

        return Inertia::render('Customer/Payments/Show', [
            'payment' => $payment->load(['reservation.facility']),
        ]);
    }
}
