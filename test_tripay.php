<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\TripayService::class);
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . config('services.tripay.api_key'),
    'Accept'        => 'application/json',
])->get($service->getBaseUrl() . '/merchant/payment-channel');

echo "HTTP Status: " . $response->status() . "\n";
echo "Response: " . $response->body() . "\n";
