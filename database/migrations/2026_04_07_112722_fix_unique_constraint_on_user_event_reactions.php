<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_event_reactions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'event_id', 'reaction']);
            $table->unique(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_event_reactions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'event_id']);
            $table->unique(['user_id', 'event_id', 'reaction']);
        });
    }
};
