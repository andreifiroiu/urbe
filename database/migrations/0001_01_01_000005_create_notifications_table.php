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
        Schema::create('event_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('frequency');
            $table->jsonb('event_ids')->default('[]');
            $table->jsonb('discovery_event_ids')->default('[]');
            $table->text('subject')->nullable();
            $table->text('body_html')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notifications');
    }
};
