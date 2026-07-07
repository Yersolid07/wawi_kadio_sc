<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $reservation = Reservation::findOrFail($validated['reservation_id']);

        // Only the reservation owner can review
        if ($reservation->user_id !== auth()->id()) abort(403);

        // Only completed reservations can be reviewed
        if ($reservation->status !== 'completed') {
            return back()->with('error', 'Anda hanya dapat memberikan ulasan untuk reservasi yang telah selesai.');
        }

        // Check if already reviewed
        if ($reservation->review()->exists()) {
            return back()->with('error', 'Anda sudah memberikan ulasan untuk reservasi ini.');
        }

        Review::create([
            'user_id' => auth()->id(),
            'reservation_id' => $validated['reservation_id'],
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'is_public' => true,
        ]);

        return back()->with('success', 'Ulasan berhasil dikirim. Terima kasih!');
    }

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== auth()->id()) abort(403);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update($validated);

        return back()->with('success', 'Ulasan berhasil diperbarui.');
    }
}
