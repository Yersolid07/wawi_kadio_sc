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
        $this->update([
            'payment_status' => 'success',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'payment_date' => now(),
        ]);

        // Update related reservation/order payment status
        if ($this->reservation) {
            $this->reservation->update(['payment_status' => 'paid']);
            if ($this->reservation->user && $this->reservation->user->email) {
                Mail::to($this->reservation->user->email)
                    ->send(new ReservationSuccessMail($this->reservation));
            }
        }

        if ($this->foodOrder) {
            $this->foodOrder->update(['payment_status' => 'paid']);
            if ($this->foodOrder->user && $this->foodOrder->user->email) {
                Mail::to($this->foodOrder->user->email)
                    ->send(new OrderSuccessMail($this->foodOrder));
            }
        }
    }
}
