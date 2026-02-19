<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->index('name', 'brands_name_idx');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->index(['parent_id', 'name'], 'categories_parent_name_idx');
            $table->index('name', 'categories_name_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['brand_id', 'updated_at'], 'products_brand_updated_idx');
            $table->index(['subcategory_id', 'updated_at'], 'products_subcategory_updated_idx');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->index(['stock_available', 'updated_at'], 'variants_stock_updated_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index('placed_at', 'orders_placed_at_idx');
        });

        Schema::table('sync_runs', function (Blueprint $table): void {
            $table->index(['resource', 'started_at'], 'sync_runs_resource_started_idx');
            $table->index(['status', 'checkpoint_updated_since'], 'sync_runs_status_checkpoint_idx');
            $table->index(['resource', 'status', 'checkpoint_updated_since'], 'sync_runs_resource_status_checkpoint_idx');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropIndex('brands_name_idx');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_parent_name_idx');
            $table->dropIndex('categories_name_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_brand_updated_idx');
            $table->dropIndex('products_subcategory_updated_idx');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex('variants_stock_updated_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_placed_at_idx');
        });

        Schema::table('sync_runs', function (Blueprint $table): void {
            $table->dropIndex('sync_runs_resource_started_idx');
            $table->dropIndex('sync_runs_status_checkpoint_idx');
            $table->dropIndex('sync_runs_resource_status_checkpoint_idx');
        });
    }
};
