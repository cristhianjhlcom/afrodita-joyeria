<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
