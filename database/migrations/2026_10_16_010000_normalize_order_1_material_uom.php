<?php

use App\Models\InventoryItem;
use App\Models\Material;
use App\Models\MaterialDispatch;
use App\Models\OrderMaterialRequirement;
use App\Models\SampleRevision;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Normalisasi SampleRevision #2
        $rev = SampleRevision::find(2);
        if ($rev && is_array($rev->material_usage)) {
            $rev->update([
                'material_usage' => [
                    'plastik' => '1.5 meter',
                    'kain anti air' => '2 meter',
                ],
            ]);
        }

        // 2. Normalisasi OrderMaterialRequirement Order #1
        $reqPlastik = OrderMaterialRequirement::where('order_id', 1)->where('item_name', 'Plastik')->first();
        if ($reqPlastik && $reqPlastik->uom === 'cm') {
            $reqPlastik->update([
                'required_quantity' => 75.00,
                'received_quantity' => (float) $reqPlastik->received_quantity > 0 ? 75.00 : 0.00,
                'uom' => 'meter',
            ]);
        }

        $reqKain = OrderMaterialRequirement::where('order_id', 1)->where('item_name', 'Kain Anti Air')->first();
        if ($reqKain && $reqKain->uom === 'cm') {
            $reqKain->update([
                'required_quantity' => 100.00,
                'received_quantity' => (float) $reqKain->received_quantity > 0 ? 100.00 : 0.00,
                'uom' => 'meter',
            ]);
        }

        // 3. Normalisasi MaterialDispatch Order #1
        $disp1 = MaterialDispatch::where('order_id', 1)->where('inventory_item_id', 1)->first();
        if ($disp1 && $disp1->uom === 'cm') {
            $disp1->update(['quantity' => 75.00, 'uom' => 'meter']);
        }
        $disp2 = MaterialDispatch::where('order_id', 1)->where('inventory_item_id', 2)->first();
        if ($disp2 && $disp2->uom === 'cm') {
            $disp2->update(['quantity' => 100.00, 'uom' => 'meter']);
        }

        // 4. Normalisasi Material & InventoryItem
        Material::whereIn('name', ['Plastik', 'Kain Anti Air'])->where('uom', 'cm')->update(['uom' => 'meter']);
        InventoryItem::where('order_id', 1)->where('uom', 'cm')->update(['uom' => 'meter']);
    }

    public function down(): void
    {
        // No-op
    }
};
