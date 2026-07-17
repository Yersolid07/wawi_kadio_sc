<?php

namespace Tests\Feature;

use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoodOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_order_out_of_stock_item()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $menuItem = MenuItem::create([
            'name' => 'Test Item',
            'category' => 'makanan',
            'is_available' => true,
            'daily_stock' => 5,
            'current_stock' => 5,
            'price' => 20000,
        ]);

        $response = $this->actingAs($user)->post(route('customer.orders.store'), [
            'order_type' => 'dine_in',
            'table_number' => '1',
            'payment_method' => 'cash',
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 10, // More than stock
                ]
            ],
        ]);

        // Expect Exception or Error redirect because stock is insufficient
        $response->assertSessionHasErrors();
        
        // Assert no order was created
        $this->assertDatabaseCount('food_orders', 0);
        
        // Assert stock remains unchanged
        $this->assertEquals(5, $menuItem->fresh()->current_stock);
    }
}
