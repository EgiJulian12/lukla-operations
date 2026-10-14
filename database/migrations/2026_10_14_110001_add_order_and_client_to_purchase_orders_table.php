<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->after('expected_at')->constrained('users')->nullOnDelete();

            $table->index(['client_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['order_id', 'client_id', 'requested_by']);
        });
    }
};
