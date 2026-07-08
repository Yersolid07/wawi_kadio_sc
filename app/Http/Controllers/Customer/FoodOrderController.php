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
            'items.*.quantity' => 'required|integer|min:1',
        ];

        if (! auth()->check()) {
            $rules['guest_name'] = 'required|string|max:255';
            $rules['guest_phone'] = 'required|string|max:50';
        }

        $validated = $request->validate($rules);

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::findOrFail($item['menu_item_id']);
            $totalAmount += $menuItem->final_price * $item['quantity'];
        }

        $orderData = [
            'total_amount' => $totalAmount,
            'order_type' => $validated['order_type'],
            'table_number' => $validated['table_number'] ?? null,
            'reservation_id' => $validated['reservation_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if (auth()->check()) {
            $orderData['user_id'] = auth()->id();
        } else {
            $orderData['guest_name'] = $validated['guest_name'];
            $orderData['guest_phone'] = $validated['guest_phone'];
            $orderData['session_id'] = session()->getId();
        }

        $order = FoodOrder::create($orderData);

        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::findOrFail($item['menu_item_id']);
            $order->items()->create([
                'menu_item_id' => $menuItem->id,
                'quantity' => $item['quantity'],
                'price' => $menuItem->final_price,
            ]);
        }

        // Notify Admins and Staff
        $adminsAndStaff = User::role(['admin', 'staff', 'manager'])->get();
        Notification::send($adminsAndStaff, new NewFoodOrder($order));

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Pesanan makanan berhasil dibuat!');
    }

    public function show(FoodOrder $order)
    {
        // Check access: if auth, must own the order. If guest, must match session ID.
        if (auth()->check()) {
            if ($order->user_id && $order->user_id !== auth()->id()) {
                // Allow admin/staff to view
                if (! auth()->user()->hasRole(['admin', 'manager', 'staff'])) {
                    abort(403);
                }
            }
        } else {
            if ($order->session_id !== session()->getId()) {
                abort(403, 'Akses ditolak. Sesi Anda tidak cocok dengan pesanan ini.');
            }
        }

        return Inertia::render('Customer/FoodOrders/Show', [
            'order' => $order->load(['items.menuItem', 'reservation.facility']),
            'isGuest' => ! $order->user_id,
        ]);
    }
}
