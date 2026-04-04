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
        Schema::create('user_event_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->string('reaction');
            $table->boolean('is_processed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'event_id', 'reaction']);
            $table->index(['user_id', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_event_reactions');
    }
};
