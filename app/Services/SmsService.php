<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS through the configured gateway.
     * Never throws into the caller — a queued notification should not crash the
     * worker because an SMS provider is down; it logs and returns false instead.
     */
    public function send(string $phone, string $message): bool
    {
        if (! config('sms.enabled')) {
            Log::info('SMS (disabled, not sent)', ['to' => $this->mask($phone), 'len' => strlen($message)]);

            return false;
        }

        return match (config('sms.gateway')) {
            'http' => $this->sendHttp($phone, $message),
            default => $this->sendLog($phone, $message),
        };
    }

    private function sendLog(string $phone, string $message): bool
    {
        Log::info('SMS (log gateway)', ['to' => $this->mask($phone), 'message' => $message]);

        return true;
    }

    private function sendHttp(string $phone, string $message): bool
    {
        $url = config('sms.http.url');

        if (! $url) {
            Log::warning('SMS http gateway has no SMS_HTTP_URL configured');

            return false;
        }

        try {
            $response = Http::timeout((int) config('sms.http.timeout', 15))
                ->when(config('sms.http.token'), fn ($http) => $http->withToken(config('sms.http.token')))
                ->asForm()
                ->post($url, [
                    config('sms.http.to_field', 'to') => $phone,
                    config('sms.http.message_field', 'message') => $message,
                    config('sms.http.from_field', 'from') => config('sms.sender'),
                ]);

            if (! $response->successful()) {
                Log::warning('SMS http gateway returned non-2xx', [
                    'to' => $this->mask($phone),
                    'status' => $response->status(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('SMS http gateway error', ['to' => $this->mask($phone), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Never log a full MSISDN (PII) — keep the country prefix and last 2 digits. */
    private function mask(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return '***';
        }

        return substr($phone, 0, 4).str_repeat('*', max(0, strlen($phone) - 6)).substr($phone, -2);
    }
}
