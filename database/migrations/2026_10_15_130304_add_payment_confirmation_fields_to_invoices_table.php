<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('payment_confirmed_at')->nullable()->after('due_at');
            $table->string('payment_method')->nullable()->after('payment_confirmed_at');
            $table->text('payment_notes')->nullable()->after('payment_method');
            $table->string('payment_proof_path')->nullable()->after('payment_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'payment_confirmed_at',
                'payment_method',
                'payment_notes',
                'payment_proof_path',
            ]);
        });
    }
};
