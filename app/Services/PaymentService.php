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
    public function createForReservation(Reservation $reservation, string $paymentMethod, ?string $paymentChannel = null): array
    {
        $paymentAmount = $reservation->total_amount;
        if ($reservation->payment_preference === 'dp') {
            $facilityType = $reservation->facility->type ?? 'homestay';
            $dpPercentage = $facilityType === 'homestay' ? 25 : 30;
            $paymentAmount = $reservation->total_amount * ($dpPercentage / 100);
        }

        $paymentData = [
            'reservation_id' => $reservation->id,
            'amount'         => $paymentAmount,
            'payment_method' => $paymentMethod,
            'payment_type'   => 'booking',
            'payment_status' => 'pending',
        ];

        $checkoutUrl = null;
        $errorMessage = null;

        if ($paymentAmount <= 0) {
            $paymentData['payment_status'] = 'success';
            $paymentData['payment_method'] = 'free'; // or whatever makes sense
            $payment = Payment::create($paymentData);
            $payment->markAsSuccess('FREE-RES-'.$reservation->id);
            return ['payment' => $payment, 'checkout_url' => null, 'error' => null];
        }

        if ($paymentMethod === 'tripay') {
            try {
                $merchantRef = $this->tripay->makeMerchantRef('RES', $reservation->id);
                
                $itemName = 'Reservasi '.($reservation->facility->name ?? 'Fasilitas');
                if ($reservation->payment_preference === 'dp') {
                    $itemName = 'DP ' . $itemName;
                }

                $result = $this->tripay->createTransaction(
                    $merchantRef,
                    (int) $paymentAmount,
                    [
                        'name'  => $reservation->customer_name ?? ($reservation->user?->name ?? 'Guest'),
                        'email' => $reservation->customer_email ?? ($reservation->user?->email ?? null),
                        'phone' => $reservation->customer_phone ?? ($reservation->user?->phone ?? null),
                    ],
                    [[
                        'sku'      => 'RES-'.$reservation->unique_code,
                        'name'     => $itemName,
                        'price'    => (int) $paymentAmount,
                        'quantity' => 1,
                    ]],
                    route('customer.reservations.show', $reservation->id),
                    $paymentChannel ?? 'QRIS'
                );

                $paymentData['transaction_id']     = $result['reference'];
                $paymentData['payment_reference']  = $merchantRef;
                $paymentData['gateway_response']   = $result;
                $checkoutUrl                       = $result['checkout_url'];
                $qrUrl                             = $result['qr_url'] ?? null;
            } catch (\Exception $e) {
                Log::error('[PaymentService] Tripay error for reservation', [
                    'reservation_id' => $reservation->id,
                    'error'          => $e->getMessage(),
                ]);
                $errorMessage = $e->getMessage();
                // Fall through — payment record still saved as pending without tripay ref
            }
        }

        $payment = Payment::create($paymentData);

        return ['payment' => $payment, 'checkout_url' => $checkoutUrl, 'qr_url' => $qrUrl ?? null, 'error' => $errorMessage];
    }

    /**
     * Create a payment for a food order.
     *
     * @return array{payment: Payment, checkout_url: string|null, error: string|null}
     */
    public function createForFoodOrder(FoodOrder $order, string $paymentMethod, array $customerInfo = [], ?string $paymentChannel = null, ?string $returnUrl = null): array
    {
        $paymentData = [
            'food_order_id'  => $order->id,
            'amount'         => $order->total_amount,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
        ];

        $checkoutUrl = null;
        $qrUrl = null;
        $errorMessage = null;

        if ($order->total_amount <= 0) {
            $paymentData['payment_status'] = 'success';
            $paymentData['payment_method'] = 'free';
            $payment = Payment::create($paymentData);
            $payment->markAsSuccess('FREE-FOD-'.$order->id);
            return ['payment' => $payment, 'checkout_url' => null, 'qr_url' => null, 'error' => null];
        }

        if ($paymentMethod === 'tripay') {
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
                    $returnUrl ?? route('customer.orders.show', $order->id),
                    $paymentChannel ?? 'QRIS'
                );

                $paymentData['transaction_id']    = $result['reference'];
                $paymentData['payment_reference'] = $merchantRef;
                $paymentData['gateway_response']  = $result;
                $checkoutUrl                      = $result['checkout_url'];
                $qrUrl                            = $result['qr_url'] ?? null;
            } catch (\Exception $e) {
                Log::error('[PaymentService] Tripay error for food order', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                $errorMessage = $e->getMessage();
            }
        }

        $payment = Payment::create($paymentData);

        return ['payment' => $payment, 'checkout_url' => $checkoutUrl, 'qr_url' => $qrUrl, 'gateway_response' => $result ?? null, 'error' => $errorMessage];
    }

    /**
     * Record a FinancialTransaction income entry for the given payment.
     * Called from Payment::markAsSuccess() observer / directly.
     */
    public static function recordIncome(Payment $payment): void
    {
        $netAmount = $payment->amount - ($payment->fee_merchant ?? 0);

        if ($payment->reservation) {
            FinancialTransaction::create([
                'type'             => 'income',
                'category'         => 'reservation',
                'amount'           => $netAmount,
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
                'amount'           => $netAmount,
                'description'      => 'Pembayaran Pesanan Cafe #'
                    .substr($payment->food_order_id, 0, 8),
                'reference_id'     => $payment->food_order_id,
                'transaction_date' => now()->toDateString(),
                'user_id'          => null,
            ]);
        }
    }
}
