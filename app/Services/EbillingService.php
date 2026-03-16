<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Events\PaymentReceived;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbillingService
{
    private function getConfig(string $settingKey, string $configKey): ?string
    {
        $value = Setting::get($settingKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        return config("ebilling.{$configKey}");
    }

    private function getMode(): string
    {
        $mode = $this->getConfig('ebilling_mode', 'mode');

        return in_array($mode, ['lab', 'prod']) ? $mode : 'lab';
    }

    private function getApiBaseUrl(): string
    {
        return config("ebilling.urls.{$this->getMode()}.api");
    }

    private function getPortalBaseUrl(): string
    {
        return config("ebilling.urls.{$this->getMode()}.portal");
    }

    private function getAuthHeader(): string
    {
        $username = $this->getConfig('ebilling_username', 'username');
        $sharedKey = $this->getConfig('ebilling_shared_key', 'shared_key');

        return 'Basic ' . base64_encode("{$username}:{$sharedKey}");
    }

    private function markOrderAsPaid(Order $order): void
    {
        $order->update([
            'payment_status' => 'success',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function createBill(Order $order, string $phone): array
    {
        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => null,
            'payment_system' => null,
            'phone' => $phone,
            'amount' => $order->total,
            'currency' => config('ebilling.currency', 'XAF'),
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        try {
            $apiUrl = $this->getApiBaseUrl() . '/api/v1/merchant/e_bills';
            $username = $this->getConfig('ebilling_username', 'username');
            $sharedKey = $this->getConfig('ebilling_shared_key', 'shared_key');

            Log::info('Ebilling createBill request', [
                'order' => $order->order_number,
                'mode' => $this->getMode(),
                'api_url' => $apiUrl,
                'username' => $username ? (substr($username, 0, 3) . '***') : '(empty)',
                'shared_key_set' => !empty($sharedKey),
            ]);

            $response = Http::timeout(config('ebilling.timeout', 30))
                ->withHeaders([
                    'Authorization' => $this->getAuthHeader(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($apiUrl, [
                    'payer_msisdn' => $phone,
                    'payer_name' => $order->shipping_name ?? $order->user?->name ?? 'Client',
                    'payer_email' => $order->guest_email ?? $order->user?->email ?? '',
                    'amount' => (int) $order->total,
                    'short_description' => 'Commande ' . $order->order_number,
                    'external_reference' => $order->order_number,
                    'expiry_period' => config('ebilling.expiry_period', 60),
                ]);

            $data = $response->json();

            Log::info('Ebilling createBill response', [
                'order' => $order->order_number,
                'status' => $response->status(),
                'body' => $data,
            ]);

            $billId = $data['e_bill']['bill_id'] ?? null;

            if ($response->successful() && $billId) {
                $transaction->update([
                    'transaction_id' => $billId,
                    'status' => 'pending',
                    'provider_response' => $data,
                ]);

                $order->update([
                    'payment_reference' => $billId,
                    'payment_status' => 'processing',
                    'payment_phone' => $phone,
                ]);

                $paymentUrl = $this->getPaymentPortalUrl($billId, $order->order_number);

                return [
                    'success' => true,
                    'bill_id' => $billId,
                    'payment_url' => $paymentUrl,
                    'message' => 'Facture créée. Vous allez être redirigé vers le portail de paiement.',
                ];
            }

            $transaction->update([
                'status' => 'failed',
                'provider_response' => $data,
                'error_message' => $data['message'] ?? $data['error'] ?? 'Erreur inconnue',
            ]);

            PaymentFailed::dispatch($order, $transaction, $data['message'] ?? 'Unknown error');

            return [
                'success' => false,
                'message' => $data['message'] ?? $data['error'] ?? 'Impossible de créer la facture',
            ];
        } catch (\Exception $e) {
            Log::error('Ebilling createBill error', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            $transaction->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw PaymentException::connectionFailed();
        }
    }

    public function getPaymentPortalUrl(string $billId, string $orderNumber): string
    {
        $portalBase = $this->getPortalBaseUrl();
        $redirectUrl = $this->getConfig('ebilling_redirect_url', 'redirect_url');
        $redirect = rtrim($redirectUrl, '/') . "/orders/{$orderNumber}?payment=return";

        return "{$portalBase}?invoice={$billId}&redirect_url=" . urlencode($redirect);
    }

    public function handleCallback(array $payload): void
    {
        Log::info('Ebilling callback received', $payload);

        $billId = $payload['billingid'] ?? $payload['bill_id'] ?? null;
        $reference = $payload['reference'] ?? null;
        $paymentSystem = $payload['paymentsystem'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!$billId) {
            Log::warning('Ebilling callback: no billingid in payload');
            return;
        }

        $transaction = PaymentTransaction::where('transaction_id', $billId)->first();

        if (!$transaction) {
            Log::warning('Ebilling callback: transaction not found', ['bill_id' => $billId]);
            return;
        }

        $transaction->update([
            'status' => 'success',
            'provider' => $paymentSystem,
            'payment_system' => $paymentSystem,
            'provider_response' => $payload,
            'completed_at' => now(),
        ]);

        $order = $transaction->order;
        $order->update(['payment_provider' => $paymentSystem]);

        $this->markOrderAsPaid($order);

        PaymentReceived::dispatch($order, $transaction);
    }
}
