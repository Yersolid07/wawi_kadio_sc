<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PosClosing;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PosClosingController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        
        // Find expected cash for today. 
        // We calculate all successful cash payments made today
        $cashPayments = Payment::where('payment_method', 'cash')
            ->where('payment_status', 'success')
            ->whereDate('created_at', $today)
            ->sum('amount');

        return Inertia::render('Staff/PosClosing', [
            'expectedCash' => (float) $cashPayments,
            'today' => $today->format('Y-m-d')
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'cash_100k' => 'required|integer|min:0',
            'cash_50k' => 'required|integer|min:0',
            'cash_20k' => 'required|integer|min:0',
            'cash_10k' => 'required|integer|min:0',
            'cash_5k' => 'required|integer|min:0',
            'cash_2k' => 'required|integer|min:0',
            'cash_1k' => 'required|integer|min:0',
            'coins' => 'required|integer|min:0',
            'note' => 'nullable|string'
        ]);

        $actualCash = 
            ($validated['cash_100k'] * 100000) +
            ($validated['cash_50k'] * 50000) +
            ($validated['cash_20k'] * 20000) +
            ($validated['cash_10k'] * 10000) +
            ($validated['cash_5k'] * 5000) +
            ($validated['cash_2k'] * 2000) +
            ($validated['cash_1k'] * 1000) +
            $validated['coins'];

        PosClosing::create([
            'user_id' => auth()->id(),
            'date' => Carbon::today(),
            'opening_balance' => 0,
            'closing_balance' => $validated['closing_balance'],
            'actual_balance' => $actualCash,
            'difference' => $actualCash - $validated['closing_balance'],
            'cash_100k' => $validated['cash_100k'],
            'cash_50k' => $validated['cash_50k'],
            'cash_20k' => $validated['cash_20k'],
            'cash_10k' => $validated['cash_10k'],
            'cash_5k' => $validated['cash_5k'],
            'cash_2k' => $validated['cash_2k'],
            'cash_1k' => $validated['cash_1k'],
            'coins' => $validated['coins'],
            'total_cash_calculated' => $actualCash,
            'note' => $validated['note'],
        ]);

        return redirect()->route('dashboard')->with('success', 'Laporan tutup kasir harian berhasil disimpan.');
    }
}
