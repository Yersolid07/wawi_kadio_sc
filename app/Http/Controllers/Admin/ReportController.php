<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AccountingExport;
use App\Exports\CafeExport;
use App\Exports\InventoryExport;
use App\Exports\ReservationsExport;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
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
        $periodTo   = $request->query('period_to',   now()->endOfMonth()->toDateString());

        // Reservations
        $reservations = Reservation::whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('payment_status', 'paid')
            ->get();
        $reservationRevenue = $reservations->sum('total_amount');

        // Food Orders
        $foodOrders = FoodOrder::with('items.menuItem')
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->whereIn('status', ['delivered', 'ready', 'preparing', 'pending', 'completed'])
            ->where('payment_status', 'paid')
            ->get();

        $foodOrderRevenue = 0;
        $ticketRevenue    = 0;

        foreach ($foodOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->menuItem && $item->menuItem->category === 'tiket') {
                    $ticketRevenue += $item->price * $item->quantity;
                } else {
                    $foodOrderRevenue += $item->price * $item->quantity;
                }
            }
        }

        // Inventory Cost — use actual total_cost from transaction records
        $inventoryOut = InventoryTransaction::with('inventory')
            ->where('type', 'out')
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->get();

        $inventoryCost = $inventoryOut->sum('total_cost');

        $totalRevenue = $reservationRevenue + $foodOrderRevenue + $ticketRevenue;
        $netProfit    = $totalRevenue - $inventoryCost;

        $stats = [
            'total_visitors'      => $reservations->sum('guest_count') ?: $reservations->count(),
            'total_reservations'  => $reservations->count(),
            'total_food_orders'   => $foodOrders->count(),
            'revenue_reservations'=> $reservationRevenue,
            'revenue_food_orders' => $foodOrderRevenue,
            'revenue_tickets'     => $ticketRevenue,
            'total_revenue'       => $totalRevenue,
            'total_expense'       => $inventoryCost,
            'net_profit'          => $netProfit,
        ];

        // Build chart data — index by date for O(1) lookups
        $resByDate   = $reservations->groupBy(fn($r) => Carbon::parse($r->created_at)->toDateString());
        $foodByDate  = $foodOrders->groupBy(fn($o) => Carbon::parse($o->created_at)->toDateString());
        $invByDate   = $inventoryOut->groupBy(fn($t) => Carbon::parse($t->created_at)->toDateString());

        $chartData   = collect();
        $periodStart = Carbon::parse($periodFrom);
        $periodEnd   = Carbon::parse($periodTo);

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $dateStr = $date->toDateString();

            $dayResRev  = ($resByDate[$dateStr] ?? collect())->sum('total_amount');
            $dayInvCost = ($invByDate[$dateStr] ?? collect())->sum('total_cost');

            $dayFoodRev   = 0;
            $dayTicketRev = 0;
            foreach (($foodByDate[$dateStr] ?? collect()) as $order) {
                foreach ($order->items as $item) {
                    if ($item->menuItem && $item->menuItem->category === 'tiket') {
                        $dayTicketRev += $item->price * $item->quantity;
                    } else {
                        $dayFoodRev += $item->price * $item->quantity;
                    }
                }
            }

            $dayRevenue = $dayResRev + $dayFoodRev + $dayTicketRev;

            $chartData->push([
                'date'                 => $date->format('d M'),
                'revenue'              => $dayRevenue,
                'revenue_reservations' => $dayResRev,
                'revenue_cafe'         => $dayFoodRev,
                'revenue_tickets'      => $dayTicketRev,
                'expense'              => $dayInvCost,
                'profit'               => $dayRevenue - $dayInvCost,
            ]);
        }

        return Inertia::render('Admin/Reports/Index', [
            'stats'     => $stats,
            'chartData' => $chartData,
            'filters'   => ['period_from' => $periodFrom, 'period_to' => $periodTo],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'type'        => 'nullable|string|in:reservations,cafe,accounting,inventory',
        ]);

        $periodFrom = $request->period_from;
        $periodTo   = $request->period_to;
        $type       = $request->type ?? 'reservations';

        return match ($type) {
            'reservations' => $this->pdfReservations($periodFrom, $periodTo),
            'cafe'         => $this->pdfCafe($periodFrom, $periodTo),
            'accounting'   => $this->pdfAccounting($periodFrom, $periodTo),
            'inventory'    => $this->pdfInventory($periodFrom, $periodTo),
        };
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'type'        => 'nullable|string|in:reservations,cafe,accounting,inventory',
        ]);

        $from = $request->period_from;
        $to   = $request->period_to;
        $type = $request->type ?? 'reservations';

        [$exportClass, $prefix] = match ($type) {
            'reservations' => [new ReservationsExport($from, $to), 'Laporan_Reservasi'],
            'cafe'         => [new CafeExport($from, $to),         'Laporan_Cafe'],
            'accounting'   => [new AccountingExport($from, $to),   'Laporan_Keuangan'],
            'inventory'    => [new InventoryExport($from, $to),    'Laporan_Inventori'],
        };

        return Excel::download($exportClass, "{$prefix}_{$from}_sd_{$to}.xlsx");
    }

    // ─── Private PDF helpers ─────────────────────────────────────────────────

    private function pdfReservations(string $period_from, string $period_to)
    {
        $reservations = Reservation::with(['user', 'facility'])
            ->whereBetween('created_at', [$period_from, $period_to.' 23:59:59'])
            ->orderBy('check_in_date')
            ->get();
        $revenue = $reservations->where('payment_status', 'paid')->sum('total_amount');

        return Pdf::loadView('reports.reservations-pdf', compact('reservations', 'revenue', 'period_from', 'period_to'))
            ->download("Laporan_Reservasi_{$period_from}_sd_{$period_to}.pdf");
    }

    private function pdfCafe(string $periodFrom, string $periodTo)
    {
        $orders = FoodOrder::with(['user', 'items.menuItem'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('payment_status', 'paid')
            ->orderBy('created_at')
            ->get();
        $revenue = $orders->sum('total_amount');

        return Pdf::loadView('reports.cafe-pdf', compact('orders', 'revenue', 'periodFrom', 'periodTo'))
            ->download("Laporan_Cafe_{$periodFrom}_sd_{$periodTo}.pdf");
    }

    private function pdfAccounting(string $period_from, string $period_to)
    {
        $transactions = FinancialTransaction::whereBetween('transaction_date', [$period_from, $period_to])
            ->orderBy('transaction_date')
            ->get();
        $income  = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');

        return Pdf::loadView('reports.accounting-pdf', compact('transactions', 'income', 'expense', 'period_from', 'period_to'))
            ->download("Laporan_Keuangan_{$period_from}_sd_{$period_to}.pdf");
    }

    private function pdfInventory(string $period_from, string $period_to)
    {
        $transactions = InventoryTransaction::with(['inventory', 'user'])
            ->whereBetween('created_at', [$period_from, $period_to.' 23:59:59'])
            ->orderBy('created_at')
            ->get();
        $totalCost = $transactions->sum('total_cost');

        return Pdf::loadView('reports.inventory-pdf', compact('transactions', 'totalCost', 'period_from', 'period_to'))
            ->download("Laporan_Inventori_{$period_from}_sd_{$period_to}.pdf");
    }
}
