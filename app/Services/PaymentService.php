<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\FoodOrder;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;

/**
 * Centralizes all payment creation logic.
 *
 * Responsibilities:
 * - Create Payment records for reservations and food orders
 * - Initiate Tripay transactions when payment_method = 'tripay'
 * - Automatically record income in FinancialTransaction on success
 */
class PaymentService
{
    public function __construct(protected TripayService $tripay) {}

    /**
     * Create a payment for a reservation.
     *
     * @return array{payment: Payment, checkout_url: string|null}
     */
    public function createForReservation(Reservation $reservation, string $paymentMethod): array
    {
        $paymentData = [
            'reservation_id' => $reservation->id,
            'amount'         => $reservation->total_amount,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
        ];

        $checkoutUrl = null;

        if ($paymentMethod === 'tripay' && $reservation->total_amount > 0) {
            try {
                $merchantRef = $this->tripay->makeMerchantRef('RES', $reservation->id);

                $result = $this->tripay->createTransaction(
                    $merchantRef,
                    (int) $reservation->total_amount,
                    [
                        'name'  => $reservation->customer_name ?? ($reservation->user?->name ?? 'Guest'),
                        'email' => $reservation->customer_email ?? ($reservation->user?->email ?? null),
                        'phone' => $reservation->customer_phone ?? ($reservation->user?->phone ?? null),
                    ],
                    [[
                        'sku'      => 'RES-'.$reservation->unique_code,
                        'name'     => 'Reservasi '.($reservation->facility->name ?? 'Fasilitas'),
                        'price'    => (int) $reservation->total_amount,
                        'quantity' => 1,
                    ]],
                    route('customer.reservations.show', $reservation->id)
                );

                $paymentData['transaction_id']     = $result['reference'];
                $paymentData['payment_reference']  = $merchantRef;
                $paymentData['gateway_response']   = $result;
                $checkoutUrl                       = $result['checkout_url'];
            } catch (\Exception $e) {
                Log::error('[PaymentService] Tripay error for reservation', [
                    'reservation_id' => $reservation->id,
                    'error'          => $e->getMessage(),
                ]);
                // Fall through — payment record still saved as pending without tripay ref
            }
        }

        $payment = Payment::create($paymentData);

        return ['payment' => $payment, 'checkout_url' => $checkoutUrl];
    }

    /**
     * Create a payment for a food order.
     *
     * @return array{payment: Payment, checkout_url: string|null}
     */
    public function createForFoodOrder(FoodOrder $order, string $paymentMethod, array $customerInfo = []): array
    {
        $paymentData = [
            'food_order_id'  => $order->id,
            'amount'         => $order->total_amount,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
        ];

        $checkoutUrl = null;

        if ($paymentMethod === 'tripay' && $order->total_amount > 0) {
            try {
                $merchantRef = $this->tripay->makeMerchantRef('FOD', $order->id);

                $tripayItems = $order->items->map(fn($item) => [
                    'sku'      => 'MNU-'.$item->menu_item_id,
                    'name'     => $item->menuItem->name ?? 'Item',
                    'price'    => (int) $item->price,
                    'quantity' => (int) $item->quantity,
                ])->toArray();

                $result = $this->tripay->createTransaction(
                    $merchantRef,
                    (int) $order->total_amount,
                    $customerInfo,
                    $tripayItems,
                    route('customer.orders.show', $order->id)
                );

                $paymentData['transaction_id']    = $result['reference'];
                $paymentData['payment_reference'] = $merchantRef;
                $paymentData['gateway_response']  = $result;
                $checkoutUrl                      = $result['checkout_url'];
            } catch (\Exception $e) {
                Log::error('[PaymentService] Tripay error for food order', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        $payment = Payment::create($paymentData);

        return ['payment' => $payment, 'checkout_url' => $checkoutUrl];
    }

    /**
     * Record a FinancialTransaction income entry for the given payment.
     * Called from Payment::markAsSuccess() observer / directly.
     */
    public static function recordIncome(Payment $payment): void
    {
        if ($payment->reservation) {
            FinancialTransaction::create([
                'type'             => 'income',
                'category'         => 'reservation',
                'amount'           => $payment->amount,
                'description'      => 'Pembayaran Reservasi — '
                    .($payment->reservation->facility->name ?? 'Fasilitas')
                    .' ('.$payment->reservation->unique_code.')',
                'reference_id'     => $payment->reservation_id,
                'transaction_date' => now()->toDateString(),
                'user_id'          => null, // system-generated
            ]);
        } elseif ($payment->foodOrder) {
            FinancialTransaction::create([
                'type'             => 'income',
                'category'         => 'cafe',
                'amount'           => $payment->amount,
                'description'      => 'Pembayaran Pesanan Cafe #'
                    .substr($payment->food_order_id, 0, 8),
                'reference_id'     => $payment->food_order_id,
                'transaction_date' => now()->toDateString(),
                'user_id'          => null,
            ]);
        }
    }
}
