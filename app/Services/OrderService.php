<?php

namespace App\Services;

use App\Models\FoodOrder;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create a new food order.
     *
     * @param array $validated Validated order data
     * @param int|null $userId User ID (if authenticated)
     * @param string|null $sessionId Session ID (for guests)
     * @return FoodOrder
     * @throws \Exception
     */
    public function createOrder(array $validated, ?int $userId, ?string $sessionId): FoodOrder
    {
        return DB::transaction(function () use ($validated, $userId, $sessionId) {
            $totalAmount = 0;
            
            // First pass: Calculate total (this is just for reference, real total is calculated below)
            foreach ($validated['items'] as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $totalAmount += $menuItem->final_price * $item['quantity'];
            }

            $orderData = [
                'total_amount' => $totalAmount,
                'order_type' => $validated['order_type'],
                'table_number' => $validated['table_number'] ?? null,
                'reservation_id' => $validated['reservation_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];

            if ($userId) {
                $orderData['user_id'] = $userId;
            } else {
                $orderData['customer_name'] = $validated['customer_name'];
                $orderData['customer_phone'] = $validated['customer_phone'];
                $orderData['session_id'] = $sessionId;
            }

            $order = FoodOrder::create($orderData);
            
            $realTotal = 0;

            foreach ($validated['items'] as $item) {
                // Pessimistic lock on MenuItem to ensure it's not concurrently disabled or stock modified
                $menuItem = MenuItem::where('id', $item['menu_item_id'])->lockForUpdate()->firstOrFail();
                
                if (!$menuItem->is_available) {
                    throw new \Exception("Menu {$menuItem->name} saat ini tidak tersedia.");
                }

                if ($menuItem->daily_stock !== null) {
                    if ($menuItem->current_stock < $item['quantity']) {
                        throw new \Exception("Stok {$menuItem->name} tidak mencukupi. Sisa stok: {$menuItem->current_stock}");
                    }
                    $menuItem->decrement('current_stock', $item['quantity']);
                }

                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->final_price,
                ]);
                
                $realTotal += $menuItem->final_price * $item['quantity'];
            }
            
            // Update the real total in case it changed during the lock
            if ($realTotal !== $totalAmount) {
                $order->update(['total_amount' => $realTotal]);
            }

            return $order;
        });
    }

    /**
     * Process POS Order (Create new or append to existing)
     */
    public function processPosOrder(array $validated, int $userId): FoodOrder
    {
        return DB::transaction(function () use ($validated, $userId) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                $totalAmount += $menuItem->final_price * $item['quantity'];
            }

            if (!empty($validated['order_id'])) {
                $order = FoodOrder::findOrFail($validated['order_id']);

                $newItemsAdded = false;
                foreach ($validated['items'] as $item) {
                    if (empty($item['is_existing'])) {
                        $menuItem = MenuItem::where('id', $item['menu_item_id'])->lockForUpdate()->firstOrFail();
                        
                        if (!$menuItem->is_available) {
                            throw new \Exception("Menu {$menuItem->name} saat ini tidak tersedia.");
                        }

                        if ($menuItem->daily_stock !== null) {
                            if ($menuItem->current_stock < $item['quantity']) {
                                throw new \Exception("Stok {$menuItem->name} tidak mencukupi. Sisa stok: {$menuItem->current_stock}");
                            }
                            $menuItem->decrement('current_stock', $item['quantity']);
                        }

                        $order->items()->create([
                            'menu_item_id' => $menuItem->id,
                            'quantity'     => $item['quantity'],
                            'price'        => $menuItem->final_price,
                            'notes'        => $item['notes'] ?? null,
                        ]);
                        $newItemsAdded = true;
                    }
                }

                $order->recalculateTotal();
                $order->update([
                    'payment_status' => 'paid',
                    'user_id'        => $userId,
                ]);

                if ($newItemsAdded) {
                    $hasFood = $order->items->contains(
                        fn($i) => $i->menuItem && $i->menuItem->category !== 'tiket'
                    );

                    if ($hasFood) {
                        $kitchenStaff = \App\Models\User::role(['staff', 'manager', 'admin'])->get();
                        \Illuminate\Support\Facades\Notification::send($kitchenStaff, new \App\Notifications\NewFoodOrder($order));
                    }
                }

            } else {
                $orderData = [
                    'total_amount'   => $totalAmount,
                    'order_type'     => $validated['order_type'],
                    'table_number'   => $validated['table_number'] ?? null,
                    'notes'          => $validated['notes'] ?? null,
                    'customer_name'  => $validated['customer_name'] ?? 'Guest Walk-in',
                    'payment_status' => 'paid',
                    'status'         => 'pending',
                    'user_id'        => $userId,
                ];

                $order = FoodOrder::create($orderData);

                $realTotal = 0;
                foreach ($validated['items'] as $item) {
                    $menuItem = MenuItem::where('id', $item['menu_item_id'])->lockForUpdate()->firstOrFail();
                    
                    if (!$menuItem->is_available) {
                        throw new \Exception("Menu {$menuItem->name} saat ini tidak tersedia.");
                    }

                    if ($menuItem->daily_stock !== null) {
                        if ($menuItem->current_stock < $item['quantity']) {
                            throw new \Exception("Stok {$menuItem->name} tidak mencukupi. Sisa stok: {$menuItem->current_stock}");
                        }
                        $menuItem->decrement('current_stock', $item['quantity']);
                    }

                    $order->items()->create([
                        'menu_item_id' => $menuItem->id,
                        'quantity'     => $item['quantity'],
                        'price'        => $menuItem->final_price,
                        'notes'        => $item['notes'] ?? null,
                    ]);
                    $realTotal += $menuItem->final_price * $item['quantity'];
                }

                if ($realTotal !== $totalAmount) {
                    $order->update(['total_amount' => $realTotal]);
                }

                $hasFood = $order->items->contains(
                    fn($i) => $i->menuItem && $i->menuItem->category !== 'tiket'
                );

                if ($hasFood) {
                    $kitchenStaff = \App\Models\User::role(['staff', 'manager', 'admin'])->get();
                    \Illuminate\Support\Facades\Notification::send($kitchenStaff, new \App\Notifications\NewFoodOrder($order));
                } else {
                    $order->update(['status' => 'completed']);
                }
            }

            return $order;
        });
    }
}
