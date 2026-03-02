<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
