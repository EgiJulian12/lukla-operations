<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom fase pra-produksi order: permintaan klien dan penawaran owner.
 *
 * Sebelum kolom ini ada, order hanya lahir dari konversi sample (FR-004) dan
 * selalu punya jumlah pasti. Permintaan klien datang lebih awal — hanya berisi
 * gambar design dan deskripsi — sehingga quantity dilonggarkan menjadi nullable
 * dan baru ditentukan saat penawaran atau konversi.
 *
 * design_images menyimpan daftar path objek (disk s3) dalam JSON agar jumlah
 * gambar design tidak terbatas pada satu kolom string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('article_id')->nullable()->after('client_id')
                ->constrained('articles')->nullOnDelete();
            $table->text('description')->nullable()->after('quantity');
            $table->json('design_images')->nullable()->after('description');
            $table->decimal('estimated_cost', 12, 2)->nullable()->after('design_images');
            $table->text('quote_notes')->nullable()->after('estimated_cost');
            $table->timestamp('quoted_at')->nullable()->after('quote_notes');
            $table->foreignId('quoted_by')->nullable()->after('quoted_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('quote_approved_at')->nullable()->after('quoted_by');
            $table->timestamp('sample_requested_at')->nullable()->after('quote_approved_at');

            $table->index('status');
        });

        // Permintaan klien belum membawa jumlah unit; quantity diisi saat
        // penawaran disetujui atau saat order massal dikonversi dari sample.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('quoted_by');
            $table->dropConstrainedForeignId('article_id');
            $table->dropColumn([
                'description',
                'design_images',
                'estimated_cost',
                'quote_notes',
                'quoted_at',
                'quote_approved_at',
                'sample_requested_at',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable(false)->change();
        });
    }
};
