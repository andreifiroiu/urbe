<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scraper_runs', function (Blueprint $table) {
            $table->string('city', 50)->nullable()->after('source');
            $table->index(['source', 'city', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('scraper_runs', function (Blueprint $table) {
            $table->dropIndex(['source', 'city', 'created_at']);
            $table->dropColumn('city');
        });
    }
};
