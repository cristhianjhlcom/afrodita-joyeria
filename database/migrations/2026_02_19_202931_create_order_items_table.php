<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_external_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('name_snapshot');
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('unit_price')->default(0);
            $table->unsignedInteger('line_total')->default(0);
            $table->timestamps();

            $table->index('variant_external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
