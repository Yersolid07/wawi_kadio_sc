<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TripayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.tripay.private_key', 'test-private-key');
    }

    protected function generateSignature(string $jsonPayload): string
    {
        return hash_hmac('sha256', $jsonPayload, 'test-private-key');
    }

    public function test_webhook_processes_paid_status_correctly()
    {
        $user = User::factory()->create();

        $facility = Facility::create([
            'name' => 'Villa A',
            'type' => 'homestay',
            'capacity' => 4,
            'price_per_day' => 100000,
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'guest_count' => 2,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => 100000,
            'payment_method' => 'tripay',
            'payment_status' => 'pending',
            'payment_reference' => 'INV-12345', // Merchant Ref
        ]);

        $payload = [
            'reference' => 'TRIPAY-REF-123',
            'merchant_ref' => 'INV-12345',
            'payment_method' => 'BRIVA',
            'payment_method_code' => 'BRIVA',
            'total_amount' => 100000,
            'fee_merchant' => 0,
            'fee_customer' => 4000,
            'total_fee' => 4000,
            'amount_received' => 100000,
            'is_closed_payment' => 1,
            'status' => 'PAID',
            'paid_at' => time(),
            'note' => 'Payment Success',
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->generateSignature($jsonPayload);

        $response = $this->postJson('/api/webhooks/tripay', $payload, [
            'X-Callback-Signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'success',
            'transaction_id' => 'TRIPAY-REF-123',
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'payment_status' => 'paid',
            'status' => 'confirmed', // Automatically confirmed when paid
        ]);
    }

    public function test_webhook_rejects_invalid_signature()
    {
        $payload = [
            'reference' => 'TRIPAY-REF-123',
            'merchant_ref' => 'INV-12345',
            'status' => 'PAID',
        ];

        $response = $this->postJson('/api/webhooks/tripay', $payload, [
            'X-Callback-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_webhook_processes_failed_status()
    {
        $user = User::factory()->create();

        $facility = Facility::create([
            'name' => 'Villa A',
            'type' => 'homestay',
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'facility_id' => $facility->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'guest_count' => 2,
            'total_amount' => 100000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => 100000,
            'payment_method' => 'tripay',
            'payment_status' => 'pending',
            'payment_reference' => 'INV-FAILED',
        ]);

        $payload = [
            'reference' => 'TRIPAY-REF-999',
            'merchant_ref' => 'INV-FAILED',
            'status' => 'FAILED',
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->generateSignature($jsonPayload);

        $response = $this->postJson('/api/webhooks/tripay', $payload, [
            'X-Callback-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'failed',
        ]);
    }
}
