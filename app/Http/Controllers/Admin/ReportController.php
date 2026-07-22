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

        // Non-financial metrics (counts)
        $reservations = Reservation::whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('payment_status', 'paid')
            ->get();
            
        $foodOrders = FoodOrder::with('items.menuItem')
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->whereIn('status', ['delivered', 'ready', 'preparing', 'pending', 'completed'])
            ->where('payment_status', 'paid')
            ->get();

        // Financial Ledger (Single Source of Truth)
        $transactions = FinancialTransaction::whereBetween('transaction_date', [$periodFrom, $periodTo])
            ->get();
            
        $totalRevenue = $transactions->where('type', 'income')->sum('amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('amount');
        
        $revenueReservations = $transactions->where('type', 'income')->where('category', 'reservation')->sum('amount');
        $revenueCafeTotal    = $transactions->where('type', 'income')->where('category', 'cafe')->sum('amount');
        $revenueOther        = $transactions->where('type', 'income')->whereNotIn('category', ['reservation', 'cafe'])->sum('amount');

        // Breakdown Cafe into Food and Tickets (Estimation based on actual orders)
        $foodItemSum = 0;
        $ticketItemSum = 0;
        foreach ($foodOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->menuItem && $item->menuItem->category === 'tiket') {
                    $ticketItemSum += $item->price * $item->quantity;
                } else {
                    $foodItemSum += $item->price * $item->quantity;
                }
            }
        }
        
        // Normalize the breakdown to match the ledger exactly
        $totalCafeItems = $foodItemSum + $ticketItemSum;
        $ticketRevenue = $totalCafeItems > 0 ? ($ticketItemSum / $totalCafeItems) * $revenueCafeTotal : 0;
        $foodOrderRevenue = $revenueCafeTotal - $ticketRevenue;

        $stats = [
            'total_visitors'      => $reservations->sum('guest_count') ?: $reservations->count(),
            'total_reservations'  => $reservations->count(),
            'total_food_orders'   => $foodOrders->count(),
            'revenue_reservations'=> $revenueReservations,
            'revenue_food_orders' => $foodOrderRevenue,
            'revenue_tickets'     => $ticketRevenue,
            'revenue_other'       => $revenueOther,
            'total_revenue'       => $totalRevenue,
            'total_expense'       => $totalExpense,
            'net_profit'          => $totalRevenue - $totalExpense,
        ];

        // Build chart data — index by date for O(1) lookups
        $transByDate = $transactions->groupBy(fn($t) => Carbon::parse($t->transaction_date)->toDateString());

        $chartData   = collect();
        $periodStart = Carbon::parse($periodFrom);
        $periodEnd   = Carbon::parse($periodTo);

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $dateStr = $date->toDateString();
            $dayTrans = $transByDate[$dateStr] ?? collect();

            $dayRevenue = $dayTrans->where('type', 'income')->sum('amount');
            $dayExpense = $dayTrans->where('type', 'expense')->sum('amount');
            
            $dayResRev  = $dayTrans->where('type', 'income')->where('category', 'reservation')->sum('amount');
            $dayCafeRev = $dayTrans->where('type', 'income')->where('category', 'cafe')->sum('amount');
            // Simplified chart: we map tickets and food together under cafe or keep them 0 if not needed individually in chart
            // Wait, the frontend might expect revenue_tickets and revenue_cafe. We'll pass them as is.

            $chartData->push([
                'date'                 => $date->format('d M'),
                'revenue'              => $dayRevenue,
                'revenue_reservations' => $dayResRev,
                'revenue_cafe'         => $dayCafeRev, 
                'revenue_tickets'      => 0, // Simplified for daily chart, mapped to cafe
                'expense'              => $dayExpense,
                'profit'               => $dayRevenue - $dayExpense,
            ]);
        }

        // Best Selling Menus
        $bestSellingMenus = \App\Models\FoodOrderItem::with('menuItem')
            ->join('food_orders', 'food_order_items.order_id', '=', 'food_orders.id')
            ->whereBetween('food_orders.created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('food_orders.payment_status', 'paid')
            ->select('food_order_items.menu_item_id', \DB::raw('SUM(food_order_items.quantity) as total_qty'), \DB::raw('SUM(food_order_items.quantity * food_order_items.price) as total_revenue'))
            ->groupBy('food_order_items.menu_item_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        // Recent Restocks
        $recentRestocks = \App\Models\InventoryTransaction::with(['inventory', 'user'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('type', 'in')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return Inertia::render('Admin/Reports/Index', [
            'stats'     => $stats,
            'chartData' => $chartData,
            'filters'   => ['period_from' => $periodFrom, 'period_to' => $periodTo],
            'bestSellingMenus' => $bestSellingMenus,
            'recentRestocks' => $recentRestocks,
        ]);
    }

    public function exportPdf(Request $request)
    {
        set_time_limit(300); // Allow up to 5 minutes for PDF generation
        ini_set('memory_limit', '512M'); // Increase memory limit for DOMPDF

        $request->validate([
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'type'        => 'nullable|string|in:reservations,cafe,accounting,inventory,comprehensive',
        ]);

        $periodFrom = $request->period_from;
        $periodTo   = $request->period_to;
        $type       = $request->type ?? 'comprehensive';

        return match ($type) {
            'comprehensive'=> $this->pdfComprehensive($periodFrom, $periodTo),
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
            'type'        => 'nullable|string|in:reservations,cafe,accounting,inventory,comprehensive',
        ]);

        $from = $request->period_from;
        $to   = $request->period_to;
        $type = $request->type ?? 'comprehensive';

        [$exportClass, $prefix] = match ($type) {
            'comprehensive'=> [new \App\Exports\ComprehensiveExport($from, $to), 'Laporan_Komprehensif'],
            'reservations' => [new ReservationsExport($from, $to), 'Laporan_Reservasi'],
            'cafe'         => [new CafeExport($from, $to),         'Laporan_Cafe'],
            'accounting'   => [new AccountingExport($from, $to),   'Laporan_Keuangan'],
            'inventory'    => [new InventoryExport($from, $to),    'Laporan_Inventori'],
        };

        return Excel::download($exportClass, "{$prefix}_{$from}_sd_{$to}.xlsx");
    }

    // ─── Private PDF helpers ─────────────────────────────────────────────────

    private function pdfComprehensive(string $periodFrom, string $periodTo)
    {
        // 1. Accounting Data
        $transactions = FinancialTransaction::with('user')->whereBetween('transaction_date', [$periodFrom, $periodTo])
            ->orderBy('transaction_date')->get();
        $income = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');
        $txsByDate = $transactions->groupBy(fn($tx) => Carbon::parse($tx->transaction_date)->format('d M Y'));

        // 2. Cafe Data
        $orders = FoodOrder::with(['user', 'items.menuItem'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('payment_status', 'paid')->orderBy('created_at')->get();
        $cafeRevenue = $orders->sum('total_amount');
        $cafeTotalOrders = $orders->count();
        $cafeTotalItems = $orders->flatMap->items->sum('quantity');
        $ordersByDate = $orders->groupBy(fn($order) => $order->created_at->format('d M Y'));

        // 3. Reservations Data
        $reservations = Reservation::with(['user', 'facility'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->orderBy('check_in_date')->get();
        $resRevenue = $reservations->where('payment_status', 'paid')->sum('total_amount');
        $resTotalGuests = $reservations->where('status', '!=', 'cancelled')->sum('guest_count');
        $resTotalCancelled = $reservations->where('status', 'cancelled')->count();
        $resTotalActive = $reservations->where('status', '!=', 'cancelled')->count();
        $resByDate = $reservations->groupBy(fn($res) => Carbon::parse($res->check_in_date)->format('d M Y'));

        // 4. Inventory Data
        $inventoryTxs = InventoryTransaction::with(['inventory', 'user'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->orderBy('created_at')->get();
        $invTotalCost = $inventoryTxs->sum('total_cost');
        $invTotalIn = $inventoryTxs->where('type', 'in')->count();
        $invTotalOut = $inventoryTxs->where('type', 'out')->count();
        $invTotalAdj = $inventoryTxs->where('type', 'adjustment')->count();
        $invTxsByDate = $inventoryTxs->groupBy(fn($tx) => $tx->created_at->format('d M Y'));

        return Pdf::loadView('reports.comprehensive-pdf', compact(
            'periodFrom', 'periodTo',
            'income', 'expense', 'txsByDate',
            'cafeRevenue', 'cafeTotalOrders', 'cafeTotalItems', 'ordersByDate',
            'resRevenue', 'resTotalGuests', 'resTotalCancelled', 'resTotalActive', 'resByDate',
            'invTotalCost', 'invTotalIn', 'invTotalOut', 'invTotalAdj', 'invTxsByDate'
        ))
        ->setPaper('a4', 'portrait')
        ->download("Laporan_Komprehensif_{$periodFrom}_sd_{$periodTo}.pdf");
    }

    private function pdfReservations(string $period_from, string $period_to)
    {
        $reservations = Reservation::with(['user', 'facility'])
            ->whereBetween('created_at', [$period_from, $period_to.' 23:59:59'])
            ->orderBy('check_in_date')
            ->get();
            
        $revenue = $reservations->where('payment_status', 'paid')->sum('total_amount');
        $totalGuests = $reservations->where('status', '!=', 'cancelled')->sum('guest_count');
        $totalCancelled = $reservations->where('status', 'cancelled')->count();
        $totalActive = $reservations->where('status', '!=', 'cancelled')->count();

        $resByDate = $reservations->groupBy(function($res) {
            return Carbon::parse($res->check_in_date)->format('d M Y');
        });

        return Pdf::loadView('reports.reservations-pdf', compact('resByDate', 'totalActive', 'totalCancelled', 'totalGuests', 'revenue', 'period_from', 'period_to'))
            ->setPaper('a4', 'landscape')
            ->download("Laporan_Reservasi_Detail_{$period_from}_sd_{$period_to}.pdf");
    }

    private function pdfCafe(string $periodFrom, string $periodTo)
    {
        $orders = FoodOrder::with(['user', 'items.menuItem'])
            ->whereBetween('created_at', [$periodFrom, $periodTo.' 23:59:59'])
            ->where('payment_status', 'paid')
            ->orderBy('created_at')
            ->get();
            
        $revenue = $orders->sum('total_amount');
        $totalOrders = $orders->count();
        $totalItems = $orders->flatMap->items->sum('quantity');
        
        $ordersByDate = $orders->groupBy(function($order) {
            return $order->created_at->format('d M Y');
        });

        return Pdf::loadView('reports.cafe-pdf', compact('ordersByDate', 'revenue', 'totalOrders', 'totalItems', 'periodFrom', 'periodTo'))
            ->setPaper('a4', 'portrait')
            ->download("Laporan_Cafe_Detail_{$periodFrom}_sd_{$periodTo}.pdf");
    }

    private function pdfAccounting(string $period_from, string $period_to)
    {
        $transactions = FinancialTransaction::with('user')->whereBetween('transaction_date', [$period_from, $period_to])
            ->orderBy('transaction_date')
            ->get();
            
        $income  = $transactions->where('type', 'income')->sum('amount');
        $expense = $transactions->where('type', 'expense')->sum('amount');

        $txsByDate = $transactions->groupBy(function($tx) {
            return Carbon::parse($tx->transaction_date)->format('d M Y');
        });

        return Pdf::loadView('reports.accounting-pdf', compact('txsByDate', 'income', 'expense', 'period_from', 'period_to'))
            ->setPaper('a4', 'portrait')
            ->download("Laporan_Keuangan_Detail_{$period_from}_sd_{$period_to}.pdf");
    }

    private function pdfInventory(string $period_from, string $period_to)
    {
        $transactions = InventoryTransaction::with(['inventory', 'user'])
            ->whereBetween('created_at', [$period_from, $period_to.' 23:59:59'])
            ->orderBy('created_at')
            ->get();
            
        $totalCost = $transactions->sum('total_cost');
        $totalIn = $transactions->where('type', 'in')->count();
        $totalOut = $transactions->where('type', 'out')->count();
        $totalAdj = $transactions->where('type', 'adjustment')->count();

        $txsByDate = $transactions->groupBy(function($tx) {
            return $tx->created_at->format('d M Y');
        });

        return Pdf::loadView('reports.inventory-pdf', compact('txsByDate', 'totalCost', 'totalIn', 'totalOut', 'totalAdj', 'period_from', 'period_to'))
            ->setPaper('a4', 'portrait')
            ->download("Laporan_Inventori_Detail_{$period_from}_sd_{$period_to}.pdf");
    }

    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'transaction_date' => 'required|date',
        ]);

        FinancialTransaction::create([
            'type' => $validated['type'],
            'category' => 'manual',
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'transaction_date' => $validated['transaction_date'],
            'user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Transaksi manual berhasil dicatat.');
    }
}
