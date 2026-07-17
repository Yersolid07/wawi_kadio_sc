<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index()
    {
        $inventories = Inventory::orderBy('name')->get();

        return Inertia::render('Admin/Inventories/Index', [
            'inventories' => $inventories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'unit' => 'required|string|max:50',
            'current_stock' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'price_per_unit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        Inventory::create($validated);

        return redirect()->back()->with('success', 'Bahan baku berhasil ditambahkan');
    }

    public function update(Request $request, Inventory $inventory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'price_per_unit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $inventory->update($validated);

        return redirect()->back()->with('success', 'Data bahan baku diperbarui');
    }

    public function transaction(Request $request, Inventory $inventory)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'total_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $inventory) {
            // Re-fetch the inventory with pessimistic locking inside transaction
            $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->firstOrFail();

            $qty = (float) $validated['quantity'];
            $type = $validated['type'];

            if ($type === 'out') {
                if ($inventory->current_stock < $qty) {
                    abort(422, 'Stok tidak mencukupi untuk pengurangan');
                }
                $inventory->current_stock -= $qty;
            } elseif ($type === 'in') {
                $inventory->current_stock += $qty;
            } else { // adjustment
                $inventory->current_stock = $qty; // adjustment sets exact stock
            }

            $inventory->save();

            $totalCost = $validated['total_cost'] ?? 0;
            if ($totalCost == 0 && $validated['type'] === 'out') {
                // If out (e.g. damaged) and cost not specified, calculate based on average price
                $totalCost = $qty * ($inventory->price_per_unit ?? 0);
            }

            $transaction = InventoryTransaction::create([
                'inventory_id' => $inventory->id,
                'type' => $type,
                'quantity' => $qty,
                'cost' => $totalCost,
                'stock_after' => $inventory->current_stock,
                'notes' => $validated['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            // If there's a cost/loss involved, record it in financial transactions
            if ($totalCost > 0) {
                $category = $type === 'in' ? 'inventory_purchase' : 'inventory_loss';
                $description = $type === 'in' ? "Pembelian Stok: {$inventory->name}" : "Kerugian Stok (Out): {$inventory->name}";
                if ($type === 'adjustment') {
                    $description = "Penyesuaian Stok: {$inventory->name}";
                }

                FinancialTransaction::create([
                    'type'             => 'expense',
                    'category'         => $category,
                    'amount'           => $totalCost,
                    'description'      => $description,
                    'reference_id'     => $transaction->id,
                    'transaction_date' => now()->toDateString(),
                    'user_id'          => auth()->id(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Transaksi stok berhasil disimpan');
    }

    public function destroy(Inventory $inventory)
    {
        $inventory->delete();

        return redirect()->back()->with('success', 'Bahan baku dihapus');
    }
}
