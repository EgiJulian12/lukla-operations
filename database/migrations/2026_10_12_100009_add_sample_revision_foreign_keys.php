<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi relasi melingkar samples -> sample_revisions yang ditunda pada
 * migrasi 2026_10_12_100007.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->foreign('current_revision_id')
                ->references('id')->on('sample_revisions')
                ->nullOnDelete();

            $table->foreign('approved_revision_id')
                ->references('id')->on('sample_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
            $table->dropForeign(['approved_revision_id']);
        });
    }
};
