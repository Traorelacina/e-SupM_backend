<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    // ========================
    // INITIATE PAYMENT
    // ========================
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'       => ['required', 'exists:orders,id'],
            'payment_method' => ['required', 'in:cinetpay,paydunya,mobile_money'],
            'phone'          => ['nullable', 'string'],
        ]);

        $order = $request->user()->orders()->findOrFail($request->order_id);

        if ($order->isPaid()) {
            return response()->json(['message' => 'Cette commande est déjà payée'], 422);
        }

        return match($request->payment_method) {
            'cinetpay'     => $this->initiateCinetPay($order, $request),
            'paydunya'     => $this->initiatePayDunya($order, $request),
            'mobile_money' => $this->initiateMobileMoney($order, $request),
            default        => response()->json(['message' => 'Méthode de paiement non supportée'], 422),
        };
    }

    // ========================
    // CINETPAY
    // ========================
    private function initiateCinetPay(Order $order, Request $request): JsonResponse
    {
        $transactionId = 'CP-' . $order->order_number . '-' . time();

        $response = Http::post('https://api-checkout.cinetpay.com/v2/payment', [
            'apikey'            => config('services.cinetpay.api_key'),
            'site_id'           => config('services.cinetpay.site_id'),
            'transaction_id'    => $transactionId,
            'amount'            => (int)$order->total,
            'currency'          => 'XOF',
            'description'       => "Commande e-Sup'M #{$order->order_number}",
            'return_url'        => config('app.frontend_url') . '/checkout/success?order=' . $order->order_number,
            'notify_url'        => route('cinetpay.webhook'),
            'customer_name'     => $order->user->name,
            'customer_email'    => $order->user->email,
            'customer_phone_number' => $request->phone ?? $order->user->phone,
            'customer_address'  => $order->address?->address_line1 ?? '',
            'customer_city'     => $order->address?->city ?? 'Abidjan',
            'customer_country'  => 'CI',
            'customer_state'    => 'CI',
            'customer_zip_code' => '00000',
        ]);

        if (!$response->successful() || ($response->json('code') !== '201')) {
            Log::error('CinetPay error', ['response' => $response->json()]);
            return response()->json(['message' => 'Erreur lors de l\'initialisation du paiement'], 500);
        }

        $order->update(['payment_reference' => $transactionId, 'payment_method' => 'cinetpay']);

        return response()->json([
            'payment_url'    => $response->json('data.payment_url'),
            'transaction_id' => $transactionId,
        ]);
    }

    // ========================
    // PAYDUNYA
    // ========================
    private function initiatePayDunya(Order $order, Request $request): JsonResponse
    {
        $response = Http::withHeaders([
            'PAYDUNYA-MASTER-KEY' => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY'=> config('services.paydunya.private_key'),
            'PAYDUNYA-TOKEN'      => config('services.paydunya.token'),
            'Content-Type'        => 'application/json',
        ])->post('https://app.paydunya.com/api/v1/checkout-invoice/create', [
            'invoice' => [
                'total_amount' => (int)$order->total,
                'description'  => "Commande e-Sup'M #{$order->order_number}",
                'items'        => $order->items->map(fn($i) => [
                    'name'       => $i->product_name,
                    'quantity'   => $i->quantity,
                    'unit_price' => $i->unit_price,
                    'total_price'=> $i->total,
                    'description'=> $i->product_name,
                ])->toArray(),
            ],
            'store' => [
                'name'    => "e-Sup'M",
                'tagline' => 'Votre supermarché en ligne',
                'phone'   => config('app.phone'),
                'postal_address' => 'Abidjan, Côte d\'Ivoire',
                'logo_url'=> config('app.url') . '/logo.png',
                'website_url' => config('app.frontend_url'),
            ],
            'actions' => [
                'cancel_url' => config('app.frontend_url') . '/checkout/cancel',
                'return_url' => config('app.frontend_url') . '/checkout/success?order=' . $order->order_number,
                'callback_url' => route('paydunya.webhook'),
            ],
            'custom_data' => ['order_id' => $order->id],
        ]);

        if (!$response->successful() || !$response->json('token')) {
            Log::error('PayDunya error', ['response' => $response->json()]);
            return response()->json(['message' => 'Erreur lors de l\'initialisation du paiement PayDunya'], 500);
        }

        $token = $response->json('token');
        $order->update(['payment_reference' => $token, 'payment_method' => 'paydunya']);

        return response()->json([
            'payment_url' => 'https://app.paydunya.com/checkout/invoice/' . $token,
            'token'       => $token,
        ]);
    }

    // ========================
    // MOBILE MONEY (generic)
    // ========================
    private function initiateMobileMoney(Order $order, Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string']]);

        // Use CinetPay for mobile money as it supports MTN, Orange, Moov
        return $this->initiateCinetPay($order, $request);
    }

    // ========================
    // VERIFY PAYMENT
    // ========================
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'       => ['required', 'exists:orders,id'],
            'transaction_id' => ['required', 'string'],
        ]);

        $order = $request->user()->orders()->findOrFail($request->order_id);

        if ($order->isPaid()) {
            return response()->json(['message' => 'Déjà payé', 'order' => $order]);
        }

        // Check status via CinetPay
        $response = Http::post('https://api-checkout.cinetpay.com/v2/payment/check', [
            'apikey'         => config('services.cinetpay.api_key'),
            'site_id'        => config('services.cinetpay.site_id'),
            'transaction_id' => $request->transaction_id,
        ]);

        $data = $response->json();

        if (isset($data['data']['status']) && $data['data']['status'] === 'ACCEPTED') {
            $this->orderService->markAsPaid($order, $request->transaction_id, $data['data']['payment_method'] ?? '');
            return response()->json(['message' => 'Paiement confirmé', 'order' => $order->fresh()]);
        }

        return response()->json(['message' => 'Paiement en attente ou échoué', 'status' => $data['data']['status'] ?? 'unknown']);
    }

    // ========================
    // WEBHOOKS
    // ========================
    public function cinetpayWebhook(Request $request): JsonResponse
    {
        Log::info('CinetPay webhook', $request->all());

        $cpm_trans_id = $request->input('cpm_trans_id');
        $cpm_result   = $request->input('cpm_result');

        if ($cpm_result !== '00') {
            return response()->json(['message' => 'Payment failed']);
        }

        // Verify with CinetPay
        $verif = Http::post('https://api-checkout.cinetpay.com/v2/payment/check', [
            'apikey'         => config('services.cinetpay.api_key'),
            'site_id'        => config('services.cinetpay.site_id'),
            'transaction_id' => $cpm_trans_id,
        ]);

        if ($verif->json('data.status') === 'ACCEPTED') {
            $order = Order::where('payment_reference', $cpm_trans_id)->first();
            if ($order && !$order->isPaid()) {
                $this->orderService->markAsPaid($order, $cpm_trans_id, 'cinetpay');
            }
        }

        return response()->json(['message' => 'OK']);
    }

    public function paydunyaWebhook(Request $request): JsonResponse
    {
        Log::info('PayDunya webhook', $request->all());

        $data = $request->json()->all();
        if (!isset($data['data']['invoice']['token'])) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $token = $data['data']['invoice']['token'];
        $status = $data['data']['invoice']['status'] ?? '';

        if ($status === 'completed') {
            $order = Order::where('payment_reference', $token)->first();
            if ($order && !$order->isPaid()) {
                $this->orderService->markAsPaid($order, $token, 'paydunya');
            }
        }

        return response()->json(['message' => 'OK']);
    }

    public function cinetpayCallback(Request $request): JsonResponse { return $this->cinetpayWebhook($request); }
    public function paydunyaCallback(Request $request): JsonResponse { return $this->paydunyaWebhook($request); }
}
