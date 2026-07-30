<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FoodOrder;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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

        $stats = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_stats', 60, function () use ($today, $thisMonth, $thisYear) {
            return [
                'total_reservations' => Reservation::count(),
                'pending_reservations' => Reservation::where('status', 'pending')->count(),
                'confirmed_today' => Reservation::where('check_in_date', $today)->where('status', 'confirmed')->count(),
                'revenue_today' => \App\Models\FinancialTransaction::where('type', 'income')->whereDate('transaction_date', today())->sum('amount'),
                'revenue_month' => \App\Models\FinancialTransaction::where('type', 'income')->whereMonth('transaction_date', $thisMonth)->whereYear('transaction_date', $thisYear)->sum('amount'),
                'total_customers' => User::role('customer')->count(),
                'active_food_orders' => FoodOrder::whereIn('status', ['pending', 'preparing', 'ready'])->count(),
                'average_rating' => Review::where('is_public', true)->avg('rating'),
            ];
        });

        $recent_reservations = Reservation::with(['user', 'facility'])
            ->latest()
            ->limit(8)
            ->get();

        $revenue_chart = \App\Models\FinancialTransaction::where('type', 'income')
            ->whereMonth('transaction_date', $thisMonth)
            ->whereYear('transaction_date', $thisYear)
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $facility_occupancy = Facility::withCount([
            'reservations as active_reservations_count' => fn ($q) => $q->whereIn('status', ['confirmed'])
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
            'pendingPayments' => Payment::with(['reservation.user'])
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
