<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'reservation_id',
        'total_amount',
        'status',
        'payment_status',
        'order_type',
        'table_number',
        'notes',
        'guest_name',
        'guest_phone',
        'session_id',
        'estimated_ready_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'estimated_ready_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FoodOrderItem::class, 'order_id');
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum(\Illuminate\Support\Facades\DB::raw('quantity * price'));
        $this->save();
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'preparing', 'ready']);
    }
}
