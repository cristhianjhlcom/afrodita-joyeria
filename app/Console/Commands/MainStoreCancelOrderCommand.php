<?php

namespace App\Console\Commands;

use App\Services\MainStore\MainStoreOrderPushService;
use Illuminate\Console\Command;

class MainStoreCancelOrderCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'main-store:orders:cancel
                            {external_order_id : External order identifier in main store}
                            {--note= : Cancellation note}
                            {--mark-refunded : Flag the cancellation as refunded in local projection}';

    /**
     * @var string
     */
    protected $description = 'Cancel an external order in the main store';

    public function handle(MainStoreOrderPushService $orderPushService): int
    {
        try {
            $externalOrderId = (string) $this->argument('external_order_id');
            $note = (string) $this->option('note');
            $markRefunded = (bool) $this->option('mark-refunded');

            $updatedOrder = $orderPushService->cancelRemoteOrder($externalOrderId, $note, $markRefunded);

            if ($updatedOrder) {
                $this->components->info("External order [{$externalOrderId}] cancelled and local order updated.");
            } else {
                $this->components->info("External order [{$externalOrderId}] cancelled remotely. No matching local order found.");
            }

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }
    }
}
