<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\FoodOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with(['reservation.user', 'reservation.facility', 'foodOrder.user'])
            ->when($request->status, fn($q) => $q->where('payment_status', $request->status))
            ->when($request->method, fn($q) => $q->where('payment_method', $request->method))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_today' => Payment::whereDate('created_at', today())->where('payment_status', 'success')->sum('amount'),
            'total_month' => Payment::whereMonth('created_at', now()->month)->where('payment_status', 'success')->sum('amount'),
            'pending_count' => Payment::where('payment_status', 'pending')->count(),
        ];

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['status', 'method', 'date_from', 'date_to']),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'nullable|exists:reservations,id',
            'food_order_id' => 'nullable|exists:food_orders,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,credit_card,ewallet,tripay,midtrans',
            'proof_image' => 'nullable|image|max:3072',
            'note' => 'nullable|string',
        ]);

        if ($request->hasFile('proof_image')) {
            $validated['proof_image'] = $request->file('proof_image')->store('payments/proofs', 'public');
        }

        $validated['payment_status'] = $validated['payment_method'] === 'cash'
            ? 'success'   // cash is instant
            : 'pending';

        $payment = Payment::create($validated);

        // If cash, mark as paid immediately
        if ($payment->payment_status === 'success') {
            $payment->markAsSuccess();
        }

        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function verify(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validated['action'] === 'approve') {
            $payment->markAsSuccess();
            return back()->with('success', 'Pembayaran berhasil diverifikasi.');
        }

        $payment->update(['payment_status' => 'failed']);
        return back()->with('success', 'Pembayaran ditolak.');
    }

    /**
     * Tripay webhook handler.
     */
    public function tripayWebhook(Request $request)
    {
        $signature = hash_hmac('sha256', $request->getContent(), config('services.tripay.private_key'));

        if ($signature !== $request->header('X-Callback-Signature')) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();

        $payment = Payment::where('transaction_id', $data['merchant_ref'])->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($data['status'] === 'PAID') {
            $payment->update([
                'gateway_response' => $data,
                'transaction_id' => $data['reference'],
            ]);
            $payment->markAsSuccess($data['reference']);
        } elseif (in_array($data['status'], ['EXPIRED', 'FAILED'])) {
            $payment->update(['payment_status' => 'failed', 'gateway_response' => $data]);
        }

        return response()->json(['success' => true]);
    }
}
