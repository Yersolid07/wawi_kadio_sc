<?php

namespace Tests\Feature;

use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class FoodOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected MenuItem $menu1;
    protected MenuItem $menu2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // roles seeded in TestCase::setUp()
        
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
        
        $this->menu1 = MenuItem::create([
            'name' => 'Nasi Goreng',
            'category' => 'makanan',
            'price' => 30000,
            'is_available' => true,
        ]);

        $this->menu2 = MenuItem::create([
            'name' => 'Es Teh Manis',
            'category' => 'minuman',
            'price' => 10000,
            'is_available' => true,
        ]);
    }

    public function test_customer_can_create_food_order()
    {
        $response = $this->actingAs($this->customer)->post(route('customer.orders.store'), [
            'order_type' => 'dine_in',
            'table_number' => '12',
            'items' => [
                [
                    'menu_item_id' => $this->menu1->id,
                    'quantity' => 2, // 60,000
                ],
                [
                    'menu_item_id' => $this->menu2->id,
                    'quantity' => 3, // 30,000
                ]
            ],
        ]);

        $this->assertDatabaseHas('food_orders', [
            'user_id' => $this->customer->id,
            'order_type' => 'dine_in',
            'table_number' => '12',
            'status' => 'pending',
            'total_amount' => 90000,
        ]);

        $order = FoodOrder::first();

        $this->assertDatabaseHas('food_order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $this->menu1->id,
            'quantity' => 2,
            'price' => 30000,
        ]);

        $this->assertDatabaseHas('food_order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $this->menu2->id,
            'quantity' => 3,
            'price' => 10000,
        ]);

        $response->assertRedirect(route('customer.orders.show', $order));
    }

    public function test_cannot_order_without_items()
    {
        $response = $this->actingAs($this->customer)->post(route('customer.orders.store'), [
            'order_type' => 'dine_in',
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, FoodOrder::count());
    }

    public function test_customer_can_view_their_own_order()
    {
        $order = FoodOrder::create([
            'user_id' => $this->customer->id,
            'order_type' => 'takeaway',
            'status' => 'pending',
            'total_amount' => 30000,
        ]);

        FoodOrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $this->menu1->id,
            'quantity' => 1,
            'price' => 30000,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', $order));
        
        $response->assertStatus(200);
    }

    public function test_customer_cannot_view_others_order()
    {
        $otherUser = User::factory()->create();
        
        $order = FoodOrder::create([
            'user_id' => $otherUser->id,
            'order_type' => 'takeaway',
            'status' => 'pending',
            'total_amount' => 30000,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', $order));
        
        $response->assertStatus(403);
    }
}
