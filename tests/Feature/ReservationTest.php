<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Role 'customer' is already seeded in TestCase::setUp()
        
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
        
        $this->facility = Facility::create([
            'name' => 'Villa A',
            'type' => 'homestay',
            'capacity' => 4,
            'price_per_day' => 1500000,
            'is_active' => true,
        ]);
    }

    public function test_customer_can_view_reservation_form()
    {
        $response = $this->actingAs($this->customer)->get(route('customer.reservations.create'));
        $response->assertStatus(200);
    }

    public function test_customer_can_create_reservation()
    {
        $checkIn = Carbon::tomorrow()->toDateString();
        $checkOut = Carbon::tomorrow()->addDays(2)->toDateString();

        $response = $this->actingAs($this->customer)->post(route('customer.reservations.store'), [
            'facility_id' => $this->facility->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
        ]);

        $this->assertDatabaseHas('reservations', [
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'check_in_date' => $checkIn . ' 00:00:00',
            'check_out_date' => $checkOut . ' 00:00:00',
            'status' => 'pending',
        ]);

        $reservation = Reservation::first();
        // 2 days * 1,500,000 = 3,000,000
        $this->assertEquals(3000000, $reservation->total_amount);

        $response->assertRedirect(route('customer.reservations.coupon', $reservation));
    }

    public function test_cannot_book_unavailable_dates()
    {
        $checkIn = Carbon::tomorrow()->toDateString();
        $checkOut = Carbon::tomorrow()->addDays(2)->toDateString();

        // Create an existing reservation
        Reservation::create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'total_amount' => 3000000,
            'status' => 'confirmed'
        ]);

        // Try to book overlapping dates
        $overlapCheckIn = Carbon::tomorrow()->addDays(1)->toDateString();
        $overlapCheckOut = Carbon::tomorrow()->addDays(3)->toDateString();

        $response = $this->actingAs($this->customer)->post(route('customer.reservations.store'), [
            'facility_id' => $this->facility->id,
            'check_in_date' => $overlapCheckIn,
            'check_out_date' => $overlapCheckOut,
            'guest_count' => 2,
        ]);

        $response->assertSessionHasErrors('check_in_date');
        $this->assertEquals(1, Reservation::count());
    }

    public function test_can_book_same_day_as_checkout()
    {
        $checkIn = Carbon::tomorrow()->toDateString();
        $checkOut = Carbon::tomorrow()->addDays(2)->toDateString(); // e.g. July 5th

        // Create an existing reservation checking out on $checkOut
        Reservation::create([
            'user_id' => $this->customer->id,
            'facility_id' => $this->facility->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'guest_count' => 2,
            'total_amount' => 3000000,
            'status' => 'confirmed'
        ]);

        // Try to book starting EXACTLY on the $checkOut date
        $newCheckOut = Carbon::parse($checkOut)->addDays(2)->toDateString();

        $response = $this->actingAs($this->customer)->post(route('customer.reservations.store'), [
            'facility_id' => $this->facility->id,
            'check_in_date' => $checkOut,
            'check_out_date' => $newCheckOut,
            'guest_count' => 2,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(2, Reservation::count());
    }
}
