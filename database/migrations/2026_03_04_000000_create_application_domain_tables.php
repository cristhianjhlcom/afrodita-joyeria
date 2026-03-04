<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('updated_at');
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'name']);
            $table->index('name');
            $table->index('updated_at');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name', 70);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 50)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('url')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'updated_at']);
            $table->index(['brand_id', 'updated_at']);
            $table->index(['category_id', 'updated_at']);
            $table->index(['subcategory_id', 'updated_at']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('code')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('sale_price')->nullable();
            $table->string('color')->nullable();
            $table->string('hex')->nullable();
            $table->string('size')->nullable();
            $table->text('primary_image_url')->nullable();
            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->integer('stock_available')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stock_available', 'updated_at']);
            $table->index('updated_at');
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->text('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'variant_id']);
            $table->index('updated_at');
        });

        Schema::create('brand_whitelists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->text('main_store_token')->nullable();
            $table->timestamps();

            $table->unique('brand_id');
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable();
            $table->string('main_store_external_order_id')->nullable()->unique();
            $table->unsignedBigInteger('external_customer_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('tax_total')->default(0);
            $table->unsignedInteger('grand_total')->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->text('cancellation_note')->nullable();
            $table->boolean('is_refunded')->default(false);
            $table->timestamps();

            $table->index('updated_at');
            $table->index('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table): void {
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

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('status')->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->timestamp('checkpoint_updated_since')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['resource', 'status']);
            $table->index(['resource', 'started_at']);
            $table->index(['status', 'checkpoint_updated_since']);
            $table->index(['resource', 'status', 'checkpoint_updated_since']);
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('name');
            $table->string('iso_code_2', 2)->nullable();
            $table->string('iso_code_3', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'updated_at']);
            $table->index('updated_at');
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('name');
            $table->string('ubigeo_code', 10)->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'ubigeo_code']);
            $table->index(['country_id', 'updated_at']);
            $table->index('updated_at');
        });

        Schema::create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('name');
            $table->string('ubigeo_code', 10)->nullable();
            $table->unsignedInteger('shipping_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['department_id', 'ubigeo_code']);
            $table->index(['country_id', 'department_id', 'updated_at']);
            $table->index('updated_at');
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('name');
            $table->string('ubigeo_code', 10)->nullable();
            $table->unsignedInteger('shipping_price')->default(0);
            $table->boolean('has_delivery_express')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['province_id', 'ubigeo_code']);
            $table->index(['country_id', 'department_id', 'province_id', 'updated_at']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('brand_whitelists');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
