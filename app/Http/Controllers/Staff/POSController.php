<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function index(): Response
    {
        $paymentChannels = \Illuminate\Support\Facades\Cache::remember('tripay_channels', 86400, function () {
            try {
                return app(\App\Services\TripayService::class)->getPaymentChannels();
            } catch (\Exception $e) {
                return [];
            }
        });

        return Inertia::render('Staff/POS/Index', [
            'paymentChannels' => $paymentChannels,
            'menuItems' => MenuItem::where('is_available', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    $item->is_out_of_stock = ($item->daily_stock !== null && $item->current_stock <= 0);
                    return $item;
                })
                ->groupBy('category'),

            /**
             * Only show orders where the customer explicitly chose "bayar di kasir" (cash).
             * - Online/Tripay orders are handled by the webhook and should NOT appear here.
             * - Failed/cancelled orders are also excluded.
             */
            'activeOrders' => FoodOrder::with(['items.menuItem', 'reservation.facility', 'user', 'payment'])
                ->where('payment_status', 'unpaid')
                ->where(function ($q) {
                    $q->whereHas('payment', fn ($sub) => $sub->where('payment_method', 'cash'))
                      ->orWhere('user_id', auth()->id());
                })
                ->latest()
                ->get(),

            'user' => auth()->user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id'             => 'nullable|exists:food_orders,id',
            'order_type'           => 'required|in:dine_in,takeaway',
            'table_number'         => 'required_if:order_type,dine_in|nullable|string|max:20',
            'customer_name'        => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1|max:100',
            'items.*.notes'        => 'nullable|string',
            'items.*.is_existing'  => 'nullable|boolean',
            // Accepted POS payment methods
            'payment_method'       => 'required|in:cash,qris,transfer,edc,ewallet',
            'payment_channel'      => 'required_if:payment_method,transfer,ewallet|nullable|string',
            'amount_paid'          => 'required_if:payment_method,cash|nullable|numeric|min:0',
        ]);

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::findOrFail($item['menu_item_id']);
            $totalAmount += $menuItem->final_price * $item['quantity'];
        }

        if ($validated['payment_method'] === 'cash' && ($validated['amount_paid'] ?? 0) < $totalAmount) {
            return back()->withErrors(['amount_paid' => 'Jumlah bayar kurang dari total tagihan.']);
        }

        $isTripay = in_array($validated['payment_method'], ['qris', 'transfer', 'ewallet']);

        try {
            // If it's Tripay, we do NOT mark it as paid immediately
            $markAsPaid = !$isTripay;
            $order = app(\App\Services\OrderService::class)->processPosOrder($validated, auth()->id(), $markAsPaid);

            if ($isTripay) {
                // Determine channel code
                $channel = $validated['payment_channel'] ?? null;
                if ($validated['payment_method'] === 'qris' && empty($channel)) {
                    $channel = 'QRIS';
                }

                $customerInfo = [
                    'name'  => $validated['customer_name'] ?? 'Walk-in Customer',
                    'email' => null,
                    'phone' => null,
                ];

                $result = app(\App\Services\PaymentService::class)
                    ->createForFoodOrder($order, 'tripay', $customerInfo, $channel, route('staff.pos.print', $order->id));

                if (!empty($result['checkout_url'])) {
                    return Inertia::location($result['checkout_url']);
                }

                if (!empty($result['error'])) {
                    throw new \Exception('Tripay error: ' . $result['error']);
                }
            }
        } catch (\Exception $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with([
            'success'        => 'Transaksi berhasil diproses.',
            'print_order_id' => $order->id,
            'change_amount'  => $validated['payment_method'] === 'cash'
                ? (($validated['amount_paid'] ?? 0) - $totalAmount)
                : 0,
        ]);
    }

    public function print(FoodOrder $order)
    {
        $order->load(['items.menuItem', 'user']);
        return view('reports.pos-receipt', compact('order'));
    }
}
