<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function tripay(Request $request)
    {
        $callbackSignature = $request->header('x-callback-signature');
        $json = $request->getContent();
        $signature = hash_hmac('sha256', $json, config('services.tripay.private_key'));

        if ($signature !== $callbackSignature) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = json_decode($json);

        if (!$data || !isset($data->merchant_ref)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $payment = Payment::where('payment_reference', $data->merchant_ref)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        if ($payment->payment_status === 'success') {
            return response()->json(['success' => true]);
        }

        if ($data->status === 'PAID') {
            $payment->update([
                'payment_status' => 'success',
                'transaction_id' => $data->reference,
                'gateway_response' => $data,
                'payment_date' => now(),
            ]);

            if ($payment->reservation) {
                $payment->reservation->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                ]);
            }

            if ($payment->foodOrder) {
                $payment->foodOrder->update([
                    'status' => 'preparing',
                ]);
            }
        } elseif (in_array($data->status, ['FAILED', 'EXPIRED'])) {
            $payment->update([
                'payment_status' => 'failed',
                'transaction_id' => $data->reference,
                'gateway_response' => $data,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
