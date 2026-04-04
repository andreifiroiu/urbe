<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->string('category_explored');
            $table->float('surprise_score')->default(0.0);
            $table->string('outcome')->nullable(); // 'interested', 'not_interested', 'ignored'
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('category_explored');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_logs');
    }
};
