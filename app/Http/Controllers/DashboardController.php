<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\FoodOrder;
use App\Models\Review;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return $this->adminDashboard();
        }

        if ($user->hasRole('staff')) {
            return $this->staffDashboard();
        }

        return $this->customerDashboard();
    }

    private function adminDashboard(): Response
    {
        $today = now()->toDateString();
        $thisMonth = now()->month;
        $thisYear = now()->year;

        $stats = [
            'total_reservations' => Reservation::count(),
            'pending_reservations' => Reservation::where('status', 'pending')->count(),
            'confirmed_today' => Reservation::where('check_in_date', $today)->where('status', 'confirmed')->count(),
            'revenue_today' => Payment::whereDate('payment_date', today())->where('payment_status', 'success')->sum('amount'),
            'revenue_month' => Payment::whereMonth('payment_date', $thisMonth)->whereYear('payment_date', $thisYear)->where('payment_status', 'success')->sum('amount'),
            'total_customers' => User::role('customer')->count(),
            'active_food_orders' => FoodOrder::whereIn('status', ['pending', 'preparing', 'ready'])->count(),
            'average_rating' => Review::where('is_public', true)->avg('rating'),
        ];

        $recent_reservations = Reservation::with(['user', 'facility'])
            ->latest()
            ->limit(8)
            ->get();

        $revenue_chart = Payment::where('payment_status', 'success')
            ->whereMonth('payment_date', $thisMonth)
            ->whereYear('payment_date', $thisYear)
            ->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $facility_occupancy = Facility::withCount([
            'reservations as active_reservations_count' => fn($q) => $q->whereIn('status', ['confirmed'])
                ->whereMonth('check_in_date', $thisMonth),
        ])->get(['id', 'name', 'type']);

        $recent_reviews = Review::with(['user', 'reservation.facility'])
            ->where('is_public', true)
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard/Admin', [
            'stats' => $stats,
            'recentReservations' => $recent_reservations,
            'revenueChart' => $revenue_chart,
            'facilityOccupancy' => $facility_occupancy,
            'recentReviews' => $recent_reviews,
        ]);
    }

    private function staffDashboard(): Response
    {
        $today = now()->toDateString();

        return Inertia::render('Dashboard/Staff', [
            'todayCheckIns' => Reservation::with(['user', 'facility'])
                ->where('check_in_date', $today)
                ->whereIn('status', ['confirmed'])
                ->get(),
            'todayCheckOuts' => Reservation::with(['user', 'facility'])
                ->where('check_out_date', $today)
                ->whereIn('status', ['confirmed'])
                ->get(),
            'activeFoodOrders' => FoodOrder::with(['user', 'items.menuItem'])
                ->whereIn('status', ['pending', 'preparing', 'ready'])
                ->latest()
                ->get(),
            'pendingPayments' => \App\Models\Payment::with(['reservation.user'])
                ->where('payment_status', 'pending')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    private function customerDashboard(): Response
    {
        $user = auth()->user();

        return Inertia::render('Dashboard/Customer', [
            'upcomingReservations' => Reservation::with('facility')
                ->where('user_id', $user->id)
                ->upcoming()
                ->get(),
            'recentReservations' => Reservation::with(['facility', 'payment', 'review'])
                ->where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(),
            'recentOrders' => FoodOrder::with('items.menuItem')
                ->where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
