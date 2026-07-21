<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Facility;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Models\Review;
use App\Models\Setting;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        // Get top active facilities ordered by reservation count
        $facilities = Facility::where('is_active', true)
            ->withCount('reservations')
            ->orderByDesc('reservations_count')
            ->take(3)
            ->get();

        // Get top available menu items
        $menuItems = MenuItem::where('is_available', true)
            ->orderByDesc('price')
            ->take(4)
            ->get();

        // Get reviews with 5 stars
        $reviews = Review::with(['user:id,name,avatar'])
            ->where('is_visible', true)
            ->where('rating', 5)
            ->latest()
            ->take(5)
            ->get();

        // Get all settings
        $settings = Setting::all()->keyBy('key')->map(function ($setting) {
            return $setting->value;
        });

        // Get banners
        $banners = Banner::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // ── Guest Order Tracking ──────────────────────────────────────────────
        // If a non-authenticated guest has an active order from this session,
        // pass the summary so Welcome.jsx can show a "resume tracking" banner.
        $guestActiveOrder = null;
        if (!auth()->check() && session()->has('guest_order_id')) {
            $guestActiveOrder = FoodOrder::where('id', session('guest_order_id'))
                ->where('session_id', session()->getId())
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->select(['id', 'status', 'payment_status', 'total_amount'])
                ->first();

            // Clean up stale session key if order is gone or complete
            if (!$guestActiveOrder) {
                session()->forget('guest_order_id');
            }
        }

        return Inertia::render('Welcome', [
            'facilities'       => $facilities,
            'menuItems'        => $menuItems,
            'reviews'          => $reviews,
            'siteSettings'     => $settings,
            'banners'          => $banners,
            'guestActiveOrder' => $guestActiveOrder,
        ]);
    }
}
