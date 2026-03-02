<?php

namespace App\Console\Commands;

use App\Services\MainStore\MainStoreOrderPushService;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class MainStoreCreateOrderCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'main-store:orders:create
                            {--payload= : Raw JSON payload for external order creation}
                            {--payload-file= : Absolute or relative path to a JSON file with payload}';

    /**
     * @var string
     */
    protected $description = 'Create an external order in the main store';

    public function handle(MainStoreOrderPushService $orderPushService): int
    {
        try {
            $payload = $this->resolvePayload();
            $order = $orderPushService->createRemoteOrder($payload);

            $this->components->info(
                sprintf(
                    'External order [%s] synced locally as order #%d.',
                    (string) $order->main_store_external_order_id,
                    $order->id
                )
            );

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePayload(): array
    {
        $inlinePayload = $this->option('payload');
        $payloadFile = $this->option('payload-file');

        if (! is_string($inlinePayload)) {
            $inlinePayload = '';
        }

        if (! is_string($payloadFile)) {
            $payloadFile = '';
        }

        if ($inlinePayload === '' && $payloadFile === '') {
            throw new RuntimeException('Provide either --payload or --payload-file.');
        }

        if ($inlinePayload !== '' && $payloadFile !== '') {
            throw new RuntimeException('Use either --payload or --payload-file, not both.');
        }

        if ($payloadFile !== '') {
            if (! is_file($payloadFile)) {
                throw new RuntimeException("The payload file [{$payloadFile}] does not exist.");
            }

            $inlinePayload = (string) file_get_contents($payloadFile);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($inlinePayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid JSON payload provided.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Payload must decode to a JSON object.');
        }

        return $payload;
    }
}
