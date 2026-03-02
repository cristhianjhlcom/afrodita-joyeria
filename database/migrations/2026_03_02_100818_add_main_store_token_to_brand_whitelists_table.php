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
        Schema::table('brand_whitelists', function (Blueprint $table) {
            $table->string('main_store_token')->nullable()->after('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_whitelists', function (Blueprint $table) {
            $table->dropColumn('main_store_token');
        });
    }
};
