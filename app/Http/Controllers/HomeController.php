<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\MenuItem;
use App\Models\Review;
use App\Models\Setting;
use Inertia\Inertia;
use Illuminate\Http\Request;

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

        return Inertia::render('Welcome', [
            'facilities' => $facilities,
            'menuItems'  => $menuItems,
            'reviews'    => $reviews,
            'siteSettings' => $settings,
        ]);
    }
}
