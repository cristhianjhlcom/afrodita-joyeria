<?php

namespace App\Services\MainStore;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MainStoreApiClient
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function fetch(string $resource, ?string $updatedSince = null, ?string $cursor = null, int $perPage = 100): array
    {
        $baseUrl = (string) config('services.main_store.base_url');
        $token = (string) config('services.main_store.token');
        $timeout = (int) config('services.main_store.timeout', 10);

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Main store integration is not configured.');
        }

        $query = [
            'per_page' => $perPage,
        ];

        if ($updatedSince !== null) {
            $query['updated_since'] = $updatedSince;
        }

        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withToken($token)
            ->timeout($timeout)
            ->retry(3, 300, function ($exception, PendingRequest $request): bool {
                return $exception instanceof ConnectionException;
            }, throw: false)
            ->get("/api/v1/sync/{$resource}", $query);

        $response->throw();

        /** @var array{data?: array<int, array<string, mixed>>, meta?: array<string, mixed>} $payload */
        $payload = $response->json();

        return [
            'data' => $payload['data'] ?? [],
            'meta' => $payload['meta'] ?? [],
        ];
    }
}
