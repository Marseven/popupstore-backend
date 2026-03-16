<?php

namespace App\Services;

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

    private function mapProvider(string $provider): string
    {
        return config("ebilling.provider_map.{$provider}", $provider);
    }

    private function markOrderAsPaid(Order $order): void
    {
        $order->update([
            'payment_status' => 'success',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function createBill(Order $order, string $provider, string $phone): array
    {
        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'payment_system' => $this->mapProvider($provider),
            'phone' => $phone,
            'amount' => $order->total,
            'currency' => config('ebilling.currency', 'XAF'),
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        try {
            $callbackUrl = $this->getConfig('ebilling_callback_url', 'callback_url');

            $response = Http::timeout(config('ebilling.timeout', 30))
                ->withHeaders([
                    'Authorization' => $this->getAuthHeader(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->getApiBaseUrl() . '/api/v1/merchant/e_bills', [
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
                    'payment_provider' => $provider,
                    'payment_phone' => $phone,
                ]);

                $paymentUrl = $this->getPaymentPortalUrl($billId, $callbackUrl);

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

            return [
                'success' => false,
                'message' => 'Erreur de connexion au service de paiement',
            ];
        }
    }

    public function ussdPush(string $billId, string $phone, string $provider): array
    {
        try {
            $response = Http::timeout(config('ebilling.timeout', 30))
                ->withHeaders([
                    'Authorization' => $this->getAuthHeader(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->getApiBaseUrl() . "/api/v1/merchant/e_bills/{$billId}/ussd_push", [
                    'payer_msisdn' => $phone,
                    'payment_system_name' => $this->mapProvider($provider),
                ]);

            $data = $response->json();

            Log::info('Ebilling ussdPush response', [
                'bill_id' => $billId,
                'status' => $response->status(),
                'body' => $data,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Push USSD envoyé. Veuillez confirmer sur votre téléphone.',
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? $data['error'] ?? 'Impossible d\'envoyer le push USSD',
            ];
        } catch (\Exception $e) {
            Log::error('Ebilling ussdPush error', [
                'bill_id' => $billId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur de connexion au service de paiement',
            ];
        }
    }

    public function getPaymentPortalUrl(string $billId, ?string $redirectUrl = null): string
    {
        $portalBase = $this->getPortalBaseUrl();
        $redirect = $redirectUrl ?? $this->getConfig('ebilling_callback_url', 'callback_url');

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
            'payment_system' => $paymentSystem,
            'provider_response' => $payload,
            'completed_at' => now(),
        ]);

        $this->markOrderAsPaid($transaction->order);
    }
}
