<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apify_usage_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_id', 200);
            $table->string('run_id', 200);
            $table->string('query', 500)->nullable();
            $table->integer('events_returned')->default(0);
            $table->decimal('cost_usd', 8, 4)->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apify_usage_log');
    }
};
