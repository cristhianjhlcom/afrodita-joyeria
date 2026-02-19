<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
