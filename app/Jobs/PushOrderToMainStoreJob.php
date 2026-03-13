<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Storefront\OrderPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushOrderToMainStoreJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public int $orderId) {}

    /**
     * Execute the job.
     */
    public function handle(OrderPushService $pushService): void
    {
        $order = Order::query()
            ->with('items')
            ->find($this->orderId);

        if (! $order) {
            return;
        }

        if ($order->payment_status !== 'paid') {
            return;
        }

        if ($order->source !== 'local_checkout') {
            return;
        }

        $result = $pushService->push($order);

        $pushService->applyPushResult($order, $result);
    }
}
