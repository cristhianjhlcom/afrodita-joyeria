<?php

namespace App\Console\Commands;

use App\Jobs\PushOrderToMainStoreJob;
use App\Models\Order;
use Illuminate\Console\Command;

class RetryMainStoreOrderPushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'main-store:orders:retry {--limit=50 : Max orders to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue retries for failed main store order pushes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $orders = Order::query()
            ->where('source', 'local_checkout')
            ->where('payment_status', 'paid')
            ->whereIn('push_status', ['failed', 'pending'])
            ->where('push_attempts', '<', 5)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get(['id']);

        foreach ($orders as $order) {
            PushOrderToMainStoreJob::dispatch($order->id);
        }

        $this->components->info(sprintf('Dispatched %d order push retries.', $orders->count()));

        return self::SUCCESS;
    }
}
