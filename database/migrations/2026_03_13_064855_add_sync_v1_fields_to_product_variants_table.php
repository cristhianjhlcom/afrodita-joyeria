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
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->boolean('include_in_merchant')->default(true)->after('size');
            $table->string('gtin')->nullable()->after('include_in_merchant');
            $table->string('mpn')->nullable()->after('gtin');
            $table->string('google_product_category')->nullable()->after('mpn');
            $table->timestamp('sale_price_starts_at')->nullable()->after('google_product_category');
            $table->timestamp('sale_price_ends_at')->nullable()->after('sale_price_starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn([
                'include_in_merchant',
                'gtin',
                'mpn',
                'google_product_category',
                'sale_price_starts_at',
                'sale_price_ends_at',
            ]);
        });
    }
};
