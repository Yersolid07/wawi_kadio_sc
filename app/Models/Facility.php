<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'description',
        'capacity',
        'price_per_day',
        'price_per_hour',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_per_day' => 'decimal:2',
        'price_per_hour' => 'decimal:2',
        'capacity' => 'integer',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasManyThrough(Review::class, Reservation::class);
    }

    /**
     * Check if facility is available for the given date range.
     */
    public function isAvailable(string $checkIn, string $checkOut, ?string $excludeReservationId = null): bool
    {
        $query = $this->reservations()
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                // To check overlap: (StartA < EndB) AND (EndA > StartB)
                $q->whereDate('check_in_date', '<', $checkOut)
                  ->whereDate('check_out_date', '>', $checkIn);
            });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->count() === 0;
    }

    /**
     * Get image URL or default placeholder.
     */
    public function getImageAttribute(): string
    {
        if ($this->image_url && str_starts_with($this->image_url, 'http')) {
            return $this->image_url;
        }

        return $this->image_url
            ? asset('storage/' . $this->image_url)
            : asset('images/facility-placeholder.jpg');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
