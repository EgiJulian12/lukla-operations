<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaruan skema untuk Versioning Sample (parent_revision & foto hasil)
 * serta penegasan relasi 1-to-1 Order dan Artikel (FR-001, FR-003, FR-004, Skenario Uji 04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_revisions', function (Blueprint $table) {
            $table->foreignId('parent_revision_id')
                ->nullable()
                ->after('sample_id')
                ->constrained('sample_revisions')
                ->nullOnDelete();

            $table->string('result_image_path')
                ->nullable()
                ->after('material_usage');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('client_id')
                ->unique()
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });

        Schema::table('sample_revisions', function (Blueprint $table) {
            $table->dropForeign(['parent_revision_id']);
            $table->dropColumn(['parent_revision_id', 'result_image_path']);
        });
    }
};
