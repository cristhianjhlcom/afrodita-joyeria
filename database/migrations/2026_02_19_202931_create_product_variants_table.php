<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable();
            $table->string('sku')->unique()->nullable();
            $table->string('code')->unique()->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('sale_price')->nullable();
            $table->string('color')->nullable();
            $table->string('hex')->nullable();
            $table->string('size')->nullable();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('stock_available')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
