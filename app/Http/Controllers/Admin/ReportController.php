<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReservationsExport;
use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\InventoryTransaction;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $periodFrom = $request->query('period_from', now()->startOfMonth()->toDateString());
        $periodTo = $request->query('period_to', now()->endOfMonth()->toDateString());

        // Reservations
        $reservations = Reservation::whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->whereIn('payment_status', ['paid'])
            ->get();
        $reservationRevenue = $reservations->sum('total_amount');

        // Food Orders
        $foodOrders = FoodOrder::with('items.menuItem')
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->whereIn('status', ['delivered', 'ready', 'preparing', 'pending', 'completed'])
            ->whereIn('payment_status', ['paid'])
            ->get();

        $foodOrderRevenue = 0;
        $ticketRevenue = 0;

        foreach ($foodOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->menuItem && $item->menuItem->category === 'tiket') {
                    $ticketRevenue += $item->price * $item->quantity;
                } else {
                    $foodOrderRevenue += $item->price * $item->quantity;
                }
            }
        }

        // Inventory Cost (Out transactions * average price, or just sum of price_per_unit at transaction if we had it. We'll estimate based on current price)
        $inventoryOut = InventoryTransaction::with('inventory')
            ->where('type', 'out')
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->get();

        $inventoryCost = $inventoryOut->sum(function ($transaction) {
            return $transaction->quantity * ($transaction->inventory->price_per_unit ?? 0);
        });

        $totalRevenue = $reservationRevenue + $foodOrderRevenue + $ticketRevenue;
        $netProfit = $totalRevenue - $inventoryCost;

        $stats = [
            'total_visitors' => $reservations->sum('guest_count') ?? $reservations->count(),
            'total_reservations' => $reservations->count(),
            'total_food_orders' => $foodOrders->count(),
            'revenue_reservations' => $reservationRevenue,
            'revenue_food_orders' => $foodOrderRevenue,
            'revenue_tickets' => $ticketRevenue,
            'total_revenue' => $totalRevenue,
            'total_expense' => $inventoryCost,
            'net_profit' => $netProfit,
        ];

        // Monthly chart data (if range is wide, group by day, if small group by day)
        // Group by Date for charting
        $chartData = collect();
        $periodStart = Carbon::parse($periodFrom);
        $periodEnd = Carbon::parse($periodTo);

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $dateStr = $date->toDateString();

            $dayResRev = $reservations->where('created_at', '>=', $dateStr.' 00:00:00')
                ->where('created_at', '<=', $dateStr.' 23:59:59')
                ->sum('total_amount');

            $dayFoodRev = 0;
            $dayTicketRev = 0;
            $dayOrders = $foodOrders->where('created_at', '>=', $dateStr.' 00:00:00')
                ->where('created_at', '<=', $dateStr.' 23:59:59');

            foreach ($dayOrders as $order) {
                foreach ($order->items as $item) {
                    if ($item->menuItem && $item->menuItem->category === 'tiket') {
                        $dayTicketRev += $item->price * $item->quantity;
                    } else {
                        $dayFoodRev += $item->price * $item->quantity;
                    }
                }
            }

            $dayInvCost = $inventoryOut->where('created_at', '>=', $dateStr.' 00:00:00')
                ->where('created_at', '<=', $dateStr.' 23:59:59')
                ->sum(function ($t) {
                    return $t->quantity * ($t->inventory->price_per_unit ?? 0);
                });

            $chartData->push([
                'date' => $date->format('d M'),
                'revenue' => $dayResRev + $dayFoodRev + $dayTicketRev,
                'expense' => $dayInvCost,
                'profit' => ($dayResRev + $dayFoodRev + $dayTicketRev) - $dayInvCost,
            ]);
        }

        return Inertia::render('Admin/Reports/Index', [
            'stats' => $stats,
            'chartData' => $chartData,
            'filters' => ['period_from' => $periodFrom, 'period_to' => $periodTo],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
        ]);

        $periodFrom = $request->period_from;
        $periodTo = $request->period_to;

        $reservations = Reservation::with(['user', 'facility'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->orderBy('check_in_date')
            ->get();

        $revenue = $reservations->where('payment_status', 'paid')->sum('total_amount');

        $pdf = Pdf::loadView('reports.reservations-pdf', [
            'reservations' => $reservations,
            'revenue' => $revenue,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ]);

        return $pdf->download('Laporan_Reservasi_'.$periodFrom.'_sd_'.$periodTo.'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
        ]);

        $fileName = 'Laporan_Reservasi_'.$request->period_from.'_sd_'.$request->period_to.'.xlsx';

        return Excel::download(
            new ReservationsExport($request->period_from, $request->period_to),
            $fileName
        );
    }
}
