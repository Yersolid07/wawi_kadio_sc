<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Models\QRCode;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NewFoodOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class FoodOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Customer/FoodOrders/Index', [
            'orders' => FoodOrder::with(['items.menuItem'])
                ->where('user_id', auth()->id())
                ->latest()
                ->paginate(10),
        ]);
    }

    public function create(Request $request)
    {
        $activeReservations = [];
        if (auth()->check()) {
            $activeReservations = Reservation::with('facility')
                ->where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->get();
        }

        return Inertia::render('Customer/FoodOrders/Create', [
            'menuItems' => MenuItem::where('is_available', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('category'),
            'reservationId' => $request->reservation_id,
            'activeReservations' => $activeReservations,
            'qrCodes' => QRCode::all(),
            'isAuthenticated' => auth()->check(),
            'user' => auth()->user(),
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'order_type' => 'required|in:dine_in,takeaway,room_service',
            'table_number' => 'required_if:order_type,dine_in|nullable|string|max:20',
            'reservation_id' => 'required_if:order_type,room_service|nullable|exists:reservations,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'payment_method' => 'required_unless:order_type,room_service|in:tripay,cash',
        ];

        if (! auth()->check()) {
            $rules['customer_name'] = 'required|string|max:255';
            $rules['customer_phone'] = 'required|string|max:50';
        }

        $validated = $request->validate($rules);

        // Security check for room_service
        if ($validated['order_type'] === 'room_service') {
            if (empty($validated['reservation_id'])) {
                return back()->withErrors(['reservation_id' => 'Reservasi harus dipilih untuk layanan kamar.']);
            }
            if (!auth()->check()) {
                abort(403, 'Anda harus login untuk memesan layanan kamar.');
            }
            
            $reservation = Reservation::findOrFail($validated['reservation_id']);
            if ($reservation->user_id !== auth()->id()) {
                abort(403, 'Anda tidak berhak memesan untuk reservasi ini.');
            }
        }

        try {
            $order = app(\App\Services\OrderService::class)
                ->createOrder(
                    $validated,
                    auth()->id(),
                    auth()->check() ? null : session()->getId()
                );
        } catch (\Exception $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        // Handle Payment for non room_service
        $checkoutUrl = null;
        if ($validated['order_type'] !== 'room_service') {
            $customerInfo = auth()->check()
                ? [
                    'name'  => auth()->user()->name,
                    'email' => auth()->user()->email,
                    'phone' => auth()->user()->phone ?? null,
                ]
                : [
                    'name'  => $validated['customer_name'],
                    'email' => null, // guest email not collected for food orders
                    'phone' => $validated['customer_phone'],
                ];

            $paymentMethod = $validated['payment_method'] ?? 'cash';
            $result = app(\App\Services\PaymentService::class)
                ->createForFoodOrder($order, $paymentMethod, $customerInfo);
            $checkoutUrl = $result['checkout_url'];
        }

        // Admin & Kitchen will be notified once payment is confirmed (via Tripay Webhook or POS checkout)

        if ($checkoutUrl) {
            return Inertia::location($checkoutUrl);
        }
        
        if ($validated['order_type'] !== 'room_service' && ($validated['payment_method'] ?? '') === 'tripay') {
            return redirect()->route('customer.orders.show', $order->id)
                ->with('error', 'Gagal memproses pembayaran online. Silakan bayar di kasir.');
        }

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Pesanan makanan berhasil dibuat!');
    }

    public function show(FoodOrder $order)
    {
        $this->authorizeAccess($order);

        return Inertia::render('Customer/FoodOrders/Show', [
            'order' => $order->load(['items.menuItem', 'reservation.facility', 'payment']),
            'isGuest' => ! $order->user_id,
        ]);
    }

    private function authorizeAccess(FoodOrder $order)
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->hasAnyRole(['admin', 'manager', 'staff'])) {
                return true;
            }
            if ($order->user_id === $user->id) {
                return true;
            }
            abort(403, 'Anda tidak berhak mengakses pesanan ini.');
        } else {
            if ($order->user_id === null && $order->session_id === session()->getId()) {
                return true;
            }
            abort(403, 'Sesi Anda tidak cocok dengan pesanan ini.');
        }
    }
}
