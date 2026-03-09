<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbillingService
{
    private string $apiUrl;
    private string $apiKey;
    private string $merchantId;
    private string $callbackUrl;

    public function __construct()
    {
        $this->apiUrl = config('ebilling.api_url');
        $this->apiKey = config('ebilling.api_key');
        $this->merchantId = config('ebilling.merchant_id');
        $this->callbackUrl = config('ebilling.callback_url');
    }

    public function initiatePayment(Order $order, string $provider, string $phone): array
    {
        // Validate provider
        if (!in_array($provider, config('ebilling.supported_providers'))) {
            return ['success' => false, 'message' => 'Fournisseur de paiement non supporté'];
        }

        // Create transaction record
        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'phone' => $phone,
            'amount' => $order->total,
            'currency' => config('ebilling.currency', 'XAF'),
            'status' => 'initiated',
            'initiated_at' => now(),
        ]);

        try {
            $response = Http::timeout(config('ebilling.timeout', 30))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->apiUrl . '/payments/initiate', [
                    'merchant_id' => $this->merchantId,
                    'amount' => $order->total,
                    'currency' => config('ebilling.currency', 'XAF'),
                    'provider' => $provider,
                    'phone' => $phone,
                    'reference' => $order->order_number,
                    'description' => 'Commande ' . $order->order_number,
                    'callback_url' => $this->callbackUrl,
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['transaction_id'])) {
                $transaction->update([
                    'transaction_id' => $data['transaction_id'],
                    'status' => 'pending',
                    'provider_response' => $data,
                ]);

                $order->update([
                    'payment_reference' => $data['transaction_id'],
                    'payment_status' => 'processing',
                    'payment_provider' => $provider,
                    'payment_phone' => $phone,
                ]);

                return [
                    'success' => true,
                    'transaction_id' => $data['transaction_id'],
                    'message' => 'Paiement initié. Veuillez confirmer sur votre téléphone.',
                ];
            }

            $transaction->update([
                'status' => 'failed',
                'provider_response' => $data,
                'error_message' => $data['message'] ?? 'Erreur inconnue',
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Impossible d\'initier le paiement',
            ];
        } catch (\Exception $e) {
            Log::error('Ebilling payment error', [
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

    public function checkStatus(string $transactionId): array
    {
        try {
            $response = Http::timeout(config('ebilling.timeout', 30))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->apiUrl . '/payments/status/' . $transactionId);

            $data = $response->json();

            if ($response->successful()) {
                $transaction = PaymentTransaction::where('transaction_id', $transactionId)->first();

                if ($transaction) {
                    $newStatus = $this->mapProviderStatus($data['status'] ?? 'unknown');
                    $transaction->update([
                        'status' => $newStatus,
                        'provider_response' => $data,
                        'completed_at' => in_array($newStatus, ['success', 'failed', 'cancelled']) ? now() : null,
                    ]);

                    if ($newStatus === 'success') {
                        $this->markOrderAsPaid($transaction->order);
                    }
                }

                return ['success' => true, 'status' => $data['status'] ?? 'unknown', 'data' => $data];
            }

            return ['success' => false, 'message' => 'Impossible de vérifier le statut'];
        } catch (\Exception $e) {
            Log::error('Ebilling status check error', ['transaction_id' => $transactionId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erreur de connexion'];
        }
    }

    public function handleCallback(array $payload): void
    {
        Log::info('Ebilling callback received', $payload);

        $transactionId = $payload['transaction_id'] ?? null;
        if (!$transactionId) return;

        $transaction = PaymentTransaction::where('transaction_id', $transactionId)->first();
        if (!$transaction) {
            Log::warning('Ebilling callback: transaction not found', ['transaction_id' => $transactionId]);
            return;
        }

        $newStatus = $this->mapProviderStatus($payload['status'] ?? 'unknown');

        $transaction->update([
            'status' => $newStatus,
            'provider_response' => $payload,
            'completed_at' => now(),
        ]);

        if ($newStatus === 'success') {
            $this->markOrderAsPaid($transaction->order);
        } elseif ($newStatus === 'failed') {
            $transaction->order->update(['payment_status' => 'failed']);
        }
    }

    private function mapProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'completed', 'approved' => 'success',
            'failed', 'rejected', 'error' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'pending', 'waiting' => 'pending',
            default => 'pending',
        };
    }

    private function markOrderAsPaid(Order $order): void
    {
        $order->update([
            'payment_status' => 'success',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
