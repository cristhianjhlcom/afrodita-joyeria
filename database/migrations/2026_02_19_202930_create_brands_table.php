<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique()->nullable();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
