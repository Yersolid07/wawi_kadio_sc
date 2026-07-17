<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Database\Seeders\RoleSeeder;
use Database\Seeders\AdminUserSeeder;

class StaffScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdminUserSeeder::class); // Creates roles and users including staff
    }

    public function test_staff_can_scan_paid_reservation()
    {
        $staff = User::where('email', 'staff@wawikadio.com')->first();
        
        $customer = User::factory()->create();
        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'unique_code' => 'WK-TEST01',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        
        $response = $this->actingAs($staff)->post(route('staff.reservations.verify'), [
            'unique_code' => 'WK-TEST01'
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('reservation');
    }

    public function test_staff_cannot_scan_unpaid_midtrans_reservation()
    {
        $staff = User::where('email', 'staff@wawikadio.com')->first();
        
        $customer = User::factory()->create();
        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'unique_code' => 'WK-TEST02',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        
        Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => 150000,
            'payment_method' => 'midtrans',
            'payment_status' => 'pending',
        ]);
        
        $response = $this->actingAs($staff)->post(route('staff.reservations.verify'), [
            'unique_code' => 'WK-TEST02'
        ]);

        $response->assertSessionHasErrors(['unique_code']);
    }

    public function test_staff_can_scan_unpaid_cash_reservation()
    {
        $staff = User::where('email', 'staff@wawikadio.com')->first();
        
        $customer = User::factory()->create();
        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'unique_code' => 'WK-TEST03',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        
        Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        
        $response = $this->actingAs($staff)->post(route('staff.reservations.verify'), [
            'unique_code' => 'WK-TEST03'
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('reservation');
    }
}
