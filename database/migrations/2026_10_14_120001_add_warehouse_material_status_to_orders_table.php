<?php

use App\Enums\OrderMaterialStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('warehouse_material_status')->default(OrderMaterialStatus::EMPTY->value)->after('status');
            $table->timestamp('po_requested_at')->nullable()->after('warehouse_material_status');
            $table->timestamp('client_confirmed_at')->nullable()->after('po_requested_at');
            $table->timestamp('cutting_started_at')->nullable()->after('client_confirmed_at');
            $table->timestamp('dispatched_to_production_at')->nullable()->after('cutting_started_at');
            $table->timestamp('received_by_production_at')->nullable()->after('dispatched_to_production_at');

            $table->index(['client_id', 'warehouse_material_status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'warehouse_material_status']);
            $table->dropColumn([
                'warehouse_material_status',
                'po_requested_at',
                'client_confirmed_at',
                'cutting_started_at',
                'dispatched_to_production_at',
                'received_by_production_at',
            ]);
        });
    }
};
