<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable();
            $table->unsignedBigInteger('external_customer_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('tax_total')->default(0);
            $table->unsignedInteger('grand_total')->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
