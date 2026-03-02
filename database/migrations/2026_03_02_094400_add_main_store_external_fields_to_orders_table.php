<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('main_store_external_order_id')->nullable()->unique()->after('external_id');
            $table->text('cancellation_note')->nullable()->after('placed_at');
            $table->boolean('is_refunded')->default(false)->after('cancellation_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_main_store_external_order_id_unique');
            $table->dropColumn(['main_store_external_order_id', 'cancellation_note', 'is_refunded']);
        });
    }
};
