<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CulqiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCharge(array $payload): array
    {
        $response = $this->request()->post('/charges', $payload);

        $response->throw();

        /** @var array<string, mixed> $responsePayload */
        $responsePayload = $response->json();

        return $responsePayload;
    }

    protected function request(): PendingRequest
    {
        $baseUrl = (string) config('services.culqi.base_url');
        $secretKey = trim((string) config('services.culqi.secret_key'));
        $timeout = (int) config('services.culqi.timeout', 10);

        if ($baseUrl === '' || $secretKey === '') {
            throw new RuntimeException('Culqi integration is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.$secretKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($timeout);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string, status_code: int, response_code: string, user_message: string}
     */
    public function normalizeChargeResponse(array $payload): array
    {
        return [
            'id' => (string) Arr::get($payload, 'id', ''),
            'status_code' => (int) Arr::get($payload, 'outcome.type', 0),
            'response_code' => (string) Arr::get($payload, 'outcome.code', ''),
            'user_message' => (string) Arr::get($payload, 'outcome.user_message', ''),
        ];
    }
}
