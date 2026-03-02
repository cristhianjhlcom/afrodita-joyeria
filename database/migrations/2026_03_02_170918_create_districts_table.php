<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
