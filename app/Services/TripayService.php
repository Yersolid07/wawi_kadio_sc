<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TripayService
{
    protected $apiKey;

    protected $privateKey;

    protected $merchantCode;

    protected $mode;

    public function __construct()
    {
        $this->apiKey = config('services.tripay.api_key');
        $this->privateKey = config('services.tripay.private_key');
        $this->merchantCode = config('services.tripay.merchant_code');
        $this->mode = config('services.tripay.mode');
    }

    protected function getBaseUrl()
    {
        return $this->mode === 'production'
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';
    }

    public function getPaymentChannels()
    {
        $response = Http::withToken($this->apiKey)
            ->get($this->getBaseUrl().'/merchant/payment-channel');

        if ($response->successful()) {
            return $response->json('data');
        }

        return [];
    }

    public function requestTransaction($method, $merchantRef, $amount, $customerInfo, $orderItems, $returnUrl)
    {
        $signature = hash_hmac('sha256', $this->merchantCode.$merchantRef.$amount, $this->privateKey);

        $payload = [
            'method' => $method,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $customerInfo['name'],
            'customer_email' => $customerInfo['email'] ?? 'guest@example.com',
            'customer_phone' => $customerInfo['phone'] ?? '081234567890',
            'order_items' => $orderItems,
            'return_url' => $returnUrl,
            'expired_time' => (time() + (24 * 60 * 60)), // 24 hours
            'signature' => $signature,
        ];

        $response = Http::withToken($this->apiKey)
            ->post($this->getBaseUrl().'/transaction/create', $payload);

        if ($response->successful()) {
            return $response->json('data');
        }

        throw new \Exception('Tripay Error: '.$response->body());
    }
}
