<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZarinpalGateway
{
    public function request(int $amount, string $callbackUrl, string $description, string $mobile): array
    {
        if (config('services.zarinpal.mock')) {
            $authority = 'MOCK-'.str()->upper(str()->random(20));

            return [
                'authority' => $authority,
                'url' => $callbackUrl.'?'.http_build_query(['Authority' => $authority, 'Status' => 'OK']),
            ];
        }

        $merchantId = trim((string) config('services.zarinpal.merchant_id'));
        if ($merchantId === '') {
            throw new RuntimeException('کد پذیرنده زرین‌پال تنظیم نشده است.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300)
            ->post(config('services.zarinpal.request_url'), [
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'currency' => 'IRT',
                'callback_url' => $callbackUrl,
                'description' => $description,
                'metadata' => ['mobile' => $mobile],
            ]);

        $data = $response->json('data', []);
        if (! $response->successful() || (int) ($data['code'] ?? 0) !== 100 || empty($data['authority'])) {
            throw new RuntimeException($this->errorMessage($response->json('errors'), 'ایجاد تراکنش زرین‌پال ناموفق بود.'));
        }

        return [
            'authority' => $data['authority'],
            'url' => rtrim(config('services.zarinpal.gateway_url'), '/').'/'.$data['authority'],
        ];
    }

    public function verify(string $authority, int $amount): array
    {
        if (config('services.zarinpal.mock') && str_starts_with($authority, 'MOCK-')) {
            return ['success' => true, 'reference' => 'TEST-'.now()->format('YmdHis')];
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 300)
            ->post(config('services.zarinpal.verify_url'), [
                'merchant_id' => config('services.zarinpal.merchant_id'),
                'amount' => $amount,
                'currency' => 'IRT',
                'authority' => $authority,
            ]);

        $data = $response->json('data', []);
        $code = (int) ($data['code'] ?? 0);
        if ($response->successful() && in_array($code, [100, 101], true)) {
            return [
                'success' => true,
                'reference' => (string) ($data['ref_id'] ?? $data['card_pan'] ?? $authority),
            ];
        }

        return [
            'success' => false,
            'message' => $this->errorMessage($response->json('errors'), 'تأیید پرداخت زرین‌پال ناموفق بود.'),
        ];
    }

    private function errorMessage(mixed $errors, string $fallback): string
    {
        if (is_array($errors)) {
            return (string) ($errors['message'] ?? $errors['code'] ?? $fallback);
        }

        return $fallback;
    }
}
