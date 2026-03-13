<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('main_store_order_id')->nullable()->after('main_store_external_order_id');
            $table->string('push_status', 20)->nullable()->after('paid_at');
            $table->unsignedInteger('push_attempts')->default(0)->after('push_status');
            $table->text('push_last_error')->nullable()->after('push_attempts');
            $table->json('push_last_response')->nullable()->after('push_last_error');

            $table->index('push_status');
            $table->index('main_store_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['push_status']);
            $table->dropIndex(['main_store_order_id']);

            $table->dropColumn([
                'main_store_order_id',
                'push_status',
                'push_attempts',
                'push_last_error',
                'push_last_response',
            ]);
        });
    }
};
