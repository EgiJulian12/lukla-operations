<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan user ke klien. Hanya diisi untuk peran klien_maklon, yang
 * memakai panel /portal dan di-scope ke satu client_id (FR-025, NFR-004).
 * Akun staf internal dibiarkan NULL karena mereka bekerja lintas klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('id')
                ->constrained('clients')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('is_active');
        });
    }
};
