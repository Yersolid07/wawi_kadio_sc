<?php

namespace App\Models;

use App\Mail\OrderSuccessMail;
use App\Mail\ReservationSuccessMail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'reservation_id',
        'food_order_id',
        'amount',
        'payment_method',
        'payment_status',
        'transaction_id',
        'payment_reference',
        'proof_image',
        'gateway_response',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'payment_date' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function foodOrder(): BelongsTo
    {
        return $this->belongsTo(FoodOrder::class);
    }

    public function getProofImageUrlAttribute(): ?string
    {
        return $this->proof_image ? asset('storage/'.$this->proof_image) : null;
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('payment_status', $status);
    }

    public function markAsSuccess(?string $transactionId = null): void
    {
        if ($this->payment_status === 'success') {
            return;
        }

        $this->update([
            'payment_status' => 'success',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'payment_date'   => now(),
        ]);

        // Update related reservation/order payment status
        if ($this->reservation) {
            $this->reservation->update([
                'payment_status' => 'paid',
                'status' => 'confirmed'
            ]);
            $email = $this->reservation->user?->email ?? $this->reservation->customer_email;
            if ($email) {
                try {
                    Mail::to($email)->send(new ReservationSuccessMail($this->reservation));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('[Payment] Failed to send reservation email', ['error' => $e->getMessage()]);
                }
            }
        }

        if ($this->foodOrder) {
            $this->foodOrder->update([
                'payment_status' => 'paid',
                'status' => $this->foodOrder->status === 'pending' ? 'preparing' : $this->foodOrder->status
            ]);
            
            // Check if there are food items to notify kitchen
            $hasFood = $this->foodOrder->items->contains(
                fn($i) => $i->menuItem && $i->menuItem->category !== 'tiket'
            );

            if ($hasFood) {
                $kitchenStaff = \App\Models\User::role(['staff', 'manager', 'admin'])->get();
                \Illuminate\Support\Facades\Notification::send($kitchenStaff, new \App\Notifications\NewFoodOrder($this->foodOrder));
            } else {
                if ($this->foodOrder->status === 'pending') {
                    $this->foodOrder->update(['status' => 'completed']);
                }
            }

            if ($this->foodOrder->user?->email) {
                try {
                    Mail::to($this->foodOrder->user->email)
                        ->send(new OrderSuccessMail($this->foodOrder));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('[Payment] Failed to send food order email', ['error' => $e->getMessage()]);
                }
            }
        }

        // Auto-record income in financial transactions
        try {
            \App\Services\PaymentService::recordIncome($this);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[Payment] Failed to record financial transaction', ['error' => $e->getMessage()]);
        }
    }

    public function markAsFailed(array $gatewayResponse = []): void
    {
        if (in_array($this->payment_status, ['success', 'failed'])) {
            return;
        }

        $this->update([
            'payment_status' => 'failed',
            'gateway_response' => $gatewayResponse,
        ]);

        if ($this->reservation && $this->reservation->payment_status !== 'paid') {
            $this->reservation->update([
                'payment_status' => 'failed',
                'status' => 'cancelled'
            ]);

            // Cancel associated food orders (room service) and restore stock
            // Eager load to avoid N+1
            $this->reservation->load('foodOrders.items.menuItem');
            foreach ($this->reservation->foodOrders as $foodOrder) {
                if ($foodOrder->status !== 'cancelled' && $foodOrder->payment_status !== 'paid') {
                    $foodOrder->update([
                        'payment_status' => 'failed',
                        'status' => 'cancelled'
                    ]);

                    foreach ($foodOrder->items as $item) {
                        if ($item->menuItem && $item->menuItem->daily_stock !== null) {
                            $item->menuItem->increment('current_stock', $item->quantity);
                        }
                    }
                }
            }
        }

        if ($this->foodOrder && $this->foodOrder->payment_status !== 'paid') {
            $this->foodOrder->update([
                'payment_status' => 'failed',
                'status' => 'cancelled'
            ]);

            // Restore menu items stock — eager load to avoid N+1
            $this->foodOrder->load('items.menuItem');
            foreach ($this->foodOrder->items as $item) {
                if ($item->menuItem && $item->menuItem->daily_stock !== null) {
                    $item->menuItem->increment('current_stock', $item->quantity);
                }
            }
        }
    }
}
