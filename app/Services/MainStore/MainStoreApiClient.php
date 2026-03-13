<?php

namespace App\Services\MainStore;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MainStoreApiClient
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}|null
     */
    public function fetch(
        string $resource,
        ?string $updatedSince = null,
        ?string $cursor = null,
        int $perPage = 100,
        bool $allowMissing = false,
        ?string $token = null
    ): ?array {
        $query = [
            'per_page' => $perPage,
        ];

        if ($updatedSince !== null) {
            $query['updated_since'] = $updatedSince;
        }

        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }

        $paths = $this->resolveSyncPaths($resource);
        $lastNotFound = null;

        foreach ($paths as $path) {
            $response = $this->request($token)
                ->get("/api/v1/sync/{$path}", $query);

            if ($response->status() >= 500 && $this->shouldRetryLegacyUpdatedSince($response, $updatedSince)) {
                $legacyUpdatedSince = $this->formatLegacyUpdatedSince($updatedSince);

                if ($legacyUpdatedSince !== null && $legacyUpdatedSince !== $updatedSince) {
                    $query['updated_since'] = $legacyUpdatedSince;

                    $response = $this->request($token)
                        ->get("/api/v1/sync/{$path}", $query);
                }
            }

            if ($response->status() === 404) {
                $lastNotFound = $response;

                continue;
            }

            $response->throw();

            /** @var array{data?: mixed, meta?: array<string, mixed>} $payload */
            $payload = $response->json();

            return [
                'data' => $this->normalizeDataPayload($payload['data'] ?? []),
                'meta' => array_merge($payload['meta'] ?? [], ['resource_path' => $path]),
            ];
        }

        if ($allowMissing && $lastNotFound !== null) {
            return null;
        }

        if ($lastNotFound !== null) {
            $lastNotFound->throw();
        }

        throw new RuntimeException("Unable to resolve endpoint path for resource [{$resource}].");
    }

    protected function shouldRetryLegacyUpdatedSince(Response $response, ?string $updatedSince): bool
    {
        if ($updatedSince === null || trim($updatedSince) === '') {
            return false;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return false;
        }

        $message = (string) Arr::get($payload, 'error.message', '');

        return str_contains($message, 'Illegal operator and value combination');
    }

    protected function formatLegacyUpdatedSince(?string $updatedSince): ?string
    {
        if ($updatedSince === null || trim($updatedSince) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($updatedSince)
                ->utc()
                ->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        $response = $this->request()
            ->post('/api/v1/orders', $payload);

        $response->throw();

        /** @var array<string, mixed> $responsePayload */
        $responsePayload = $response->json();

        return $responsePayload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $externalOrderId): array
    {
        $response = $this->request()
            ->get("/api/v1/orders/{$externalOrderId}");

        $response->throw();

        /** @var array<string, mixed> $responsePayload */
        $responsePayload = $response->json();

        return $responsePayload;
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelOrder(string $externalOrderId, string $note, bool $markRefunded): array
    {
        $response = $this->request()
            ->post("/api/v1/orders/{$externalOrderId}/cancel", [
                'note' => $note,
                'mark_refunded' => $markRefunded,
            ]);

        $response->throw();

        /** @var array<string, mixed> $responsePayload */
        $responsePayload = $response->json();

        return $responsePayload;
    }

    /**
     * @return list<string>
     */
    protected function resolveSyncPaths(string $resource): array
    {
        $configured = config("services.main_store.sync_endpoints.{$resource}");

        if (is_string($configured) && $configured !== '') {
            return [$configured];
        }

        if (is_array($configured)) {
            /** @var list<string> $configuredPaths */
            $configuredPaths = array_values(array_filter(Arr::flatten($configured), fn (mixed $path): bool => is_string($path) && $path !== ''));

            if ($configuredPaths !== []) {
                return $configuredPaths;
            }
        }

        return [$resource];
    }

    protected function request(?string $tokenOverride = null): PendingRequest
    {
        $baseUrl = (string) config('services.main_store.base_url');
        $token = $tokenOverride !== null && trim($tokenOverride) !== ''
            ? trim($tokenOverride)
            : (string) config('services.main_store.token');
        $timeout = (int) config('services.main_store.timeout', 10);

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Main store integration is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withToken($token)
            ->timeout($timeout)
            ->retry(3, 300, function ($exception, PendingRequest $request): bool {
                return $exception instanceof ConnectionException;
            }, throw: false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDataPayload(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if ($data === []) {
            return [];
        }

        if (Arr::isAssoc($data)) {
            /** @var array<string, mixed> $record */
            $record = $data;

            return [$record];
        }

        /** @var array<int, array<string, mixed>> $records */
        $records = array_values(array_filter($data, fn (mixed $item): bool => is_array($item)));

        return $records;
    }
}
