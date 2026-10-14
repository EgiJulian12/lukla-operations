<?php

use App\Enums\ComponentMaterialStatus;
use App\Enums\MaterialItemType;
use App\Enums\MaterialResponsibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('component_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('item_type')->default(MaterialItemType::FABRIC->value);
            $table->string('responsibility')->default(MaterialResponsibility::CLIENT->value);
            $table->string('status')->default(ComponentMaterialStatus::EMPTY->value);
            $table->decimal('required_quantity', 14, 2)->default(0);
            $table->decimal('received_quantity', 14, 2)->default(0);
            $table->string('uom', 20)->default('meter');
            $table->string('supplier_or_source')->nullable();
            $table->string('tracking_number_or_batch')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['order_id', 'responsibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_material_requirements');
    }
};
