<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('external_ref')->unique();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->string('status', 50)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('url')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['brand_id', 'slug']);
            $table->index(['status', 'updated_at']);
            $table->index(['category_id', 'updated_at']);
            $table->index(['subcategory_id', 'updated_at']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('external_ref')->unique();
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

            $table->unique(['product_id', 'sku']);
            $table->unique(['product_id', 'code']);
            $table->index(['stock_available', 'updated_at']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->string('external_ref')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->text('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'product_variant_id']);
            $table->index('updated_at');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 70);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'updated_at']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
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

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('updated_at');
        });

        Schema::enableForeignKeyConstraints();
    }
};
