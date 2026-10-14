<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\OrderStatus;
use App\Enums\QcResult;
use App\Enums\RepairStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Models\Assembly;
use App\Models\Client;
use App\Models\DefectCategory;
use App\Models\Order;
use App\Models\QcRecord;
use App\Models\Repair;
use App\Models\User;
use App\Models\WasteRecord;
use Illuminate\Database\Seeder;

class QcFlowDemoSeeder extends Seeder
{
    /**
     * Seed sample data for testing the QC workflow.
     */
    public function run(): void
    {
        // 1. Pastikan User Roles dan Akun Penguji Ada
        $qcInspector = User::query()->firstOrCreate(
            ['email' => 'qc_inspector@lukla.test'],
            [
                'name' => 'QC Inspector Demo',
                'password' => bcrypt('password'),
            ]
        );
        if (! $qcInspector->hasRole(UserRole::QC_INSPECTOR->value)) {
            $qcInspector->assignRole(UserRole::QC_INSPECTOR->value);
        }

        $prodLead = User::query()->firstOrCreate(
            ['email' => 'production_lead@lukla.test'],
            [
                'name' => 'Kepala Produksi Demo',
                'password' => bcrypt('password'),
            ]
        );
        if (! $prodLead->hasRole(UserRole::PRODUCTION_LEAD->value)) {
            $prodLead->assignRole(UserRole::PRODUCTION_LEAD->value);
        }

        $tailor = User::query()->firstOrCreate(
            ['email' => 'tailor@lukla.test'],
            [
                'name' => 'Budi Penjahit / Repairer',
                'password' => bcrypt('password'),
            ]
        );
        if (! $tailor->hasRole(UserRole::TAILOR_OPERATOR->value)) {
            $tailor->assignRole(UserRole::TAILOR_OPERATOR->value);
        }

        // 2. Kategori Cacat Standar QC Garmen
        $defStitch = DefectCategory::query()->firstOrCreate(
            ['code' => 'DEF-STITCH'],
            ['name' => 'Jahitan Melenceng / Rusak', 'description' => 'Jahitan tidak rapi, loncat, atau putus benang', 'is_active' => true]
        );
        $defFabric = DefectCategory::query()->firstOrCreate(
            ['code' => 'DEF-FABRIC'],
            ['name' => 'Bahan Bolong / Cacat Kain', 'description' => 'Kain sobek, bolong jarum parah, atau cacat rajut', 'is_active' => true]
        );
        $defStain = DefectCategory::query()->firstOrCreate(
            ['code' => 'DEF-STAIN'],
            ['name' => 'Noda Minyak / Oli Mesin', 'description' => 'Noda pelumas mesin pada permukaan pakaian', 'is_active' => true]
        );
        $defButton = DefectCategory::query()->firstOrCreate(
            ['code' => 'DEF-BUTTON'],
            ['name' => 'Kancing / Ritsleting Rusak', 'description' => 'Aksesoris lepas, miring, atau macet', 'is_active' => true]
        );
        $defSize = DefectCategory::query()->firstOrCreate(
            ['code' => 'DEF-SIZE'],
            ['name' => 'Ukuran Tidak Sesuai Pola', 'description' => 'Toleransi ukuran melebihi batas spek garmen', 'is_active' => true]
        );

        // 3. Klien Demo
        $clientMaklon = Client::query()->firstOrCreate(
            ['code' => 'MAK-DEMO'],
            ['name' => 'PT Maklon Demo', 'type' => ClientType::MAKLON, 'email' => 'kontak@maklon-demo.test']
        );

        $clientMerdeka = Client::query()->firstOrCreate(
            ['code' => 'BRAND-MERDEKA'],
            ['name' => 'CV Merdeka Apparel', 'type' => ClientType::MAKLON, 'email' => 'info@merdeka-apparel.test']
        );

        $clientGlobal = Client::query()->firstOrCreate(
            ['code' => 'SNOHUND-INTERNAL'],
            ['name' => 'Snøhund Internal Brand', 'type' => ClientType::INTERNAL, 'email' => 'internal@snohund.test']
        );

        // 4. ORDER 1: Target Siap QC Penuh untuk Pengujian Tombol "Periksa QC"
        $order1 = Order::query()->firstOrCreate(
            ['order_number' => 'ORD-QC-DEMO-01'],
            [
                'client_id' => $clientMaklon->id,
                'work_scheme' => WorkScheme::CMT,
                'quantity' => 100,
                'status' => OrderStatus::IN_PRODUCTION,
                'due_date' => now()->addWeeks(2),
                'notes' => 'Pesanan kemeja flanel - siap diuji pemeriksaan QC.',
            ]
        );

        // Batch perakitan 1 & 2 yang belum diperiksa
        Assembly::query()->firstOrCreate(
            ['order_id' => $order1->id, 'quantity' => 35],
            [
                'client_id' => $clientMaklon->id,
                'assembled_by' => $tailor->id,
                'assembled_at' => now()->subHours(2),
                'notes' => 'Batch 1 - Selesai assembling kancing & kerah',
            ]
        );

        Assembly::query()->firstOrCreate(
            ['order_id' => $order1->id, 'quantity' => 25],
            [
                'client_id' => $clientMaklon->id,
                'assembled_by' => $tailor->id,
                'assembled_at' => now()->subMinutes(30),
                'notes' => 'Batch 2 - Lengan dan badan',
            ]
        );

        // 5. ORDER 2: Simulasi Lengkap (Lolos, Ada Tiket Repair, Ada Scrap, dan Ada Sisa Siap QC)
        $order2 = Order::query()->firstOrCreate(
            ['order_number' => 'ORD-QC-DEMO-02'],
            [
                'client_id' => $clientMerdeka->id,
                'work_scheme' => WorkScheme::FULL_PACKAGE,
                'quantity' => 80,
                'status' => OrderStatus::IN_PRODUCTION,
                'due_date' => now()->addDays(10),
                'notes' => 'Pesanan jaket parka - memiliki riwayat lolos, repair, dan afkir.',
            ]
        );

        // Batch perakitan pertama: 60 pcs (sudah di-QC)
        $assembly2A = Assembly::query()->firstOrCreate(
            ['order_id' => $order2->id, 'quantity' => 60],
            [
                'client_id' => $clientMerdeka->id,
                'assembled_by' => $tailor->id,
                'assembled_at' => now()->subDay(),
                'notes' => 'Batch 1 Jaket Parka',
            ]
        );

        // QC Record 1: 45 unit PASS
        QcRecord::query()->firstOrCreate(
            [
                'order_id' => $order2->id,
                'assembly_id' => $assembly2A->id,
                'result' => QcResult::PASS,
                'quantity' => 45,
            ],
            [
                'client_id' => $clientMerdeka->id,
                'inspected_by' => $qcInspector->id,
                'inspected_at' => now()->subHours(6),
                'notes' => 'Jahitan dan bahan rapi sempurna.',
            ]
        );

        // QC Record 2: 7 unit REPAIR (Status: PENDING - Menunggu penugasan Kepala Produksi)
        $qcRepair1 = QcRecord::query()->firstOrCreate(
            [
                'order_id' => $order2->id,
                'assembly_id' => $assembly2A->id,
                'result' => QcResult::REPAIR,
                'quantity' => 7,
                'defect_category_id' => $defStitch->id,
            ],
            [
                'client_id' => $clientMerdeka->id,
                'inspected_by' => $qcInspector->id,
                'inspected_at' => now()->subHours(5),
                'notes' => 'Jahitan obras saku melenceng 3mm.',
            ]
        );

        Repair::query()->firstOrCreate(
            ['qc_record_id' => $qcRepair1->id],
            [
                'status' => RepairStatus::PENDING,
                'assigned_repairer_id' => null,
                'notes' => 'Perlu dibongkar ulang obras saku.',
            ]
        );

        // QC Record 3: 3 unit REPAIR (Status: IN_REPAIR - Sedang dikerjakan Budi Penjahit)
        $qcRepair2 = QcRecord::query()->firstOrCreate(
            [
                'order_id' => $order2->id,
                'assembly_id' => $assembly2A->id,
                'result' => QcResult::REPAIR,
                'quantity' => 3,
                'defect_category_id' => $defStain->id,
            ],
            [
                'client_id' => $clientMerdeka->id,
                'inspected_by' => $qcInspector->id,
                'inspected_at' => now()->subHours(4),
                'notes' => 'Noda oli pada manset tangan.',
            ]
        );

        Repair::query()->firstOrCreate(
            ['qc_record_id' => $qcRepair2->id],
            [
                'status' => RepairStatus::IN_REPAIR,
                'assigned_repairer_id' => $tailor->id,
                'notes' => 'Proses pembersihan menggunakan chemical spot remover.',
            ]
        );

        // QC Record 4: 5 unit SCRAP / Afkir (Status Ditolak & Catat ke Waste)
        $qcScrap = QcRecord::query()->firstOrCreate(
            [
                'order_id' => $order2->id,
                'assembly_id' => $assembly2A->id,
                'result' => QcResult::SCRAP,
                'quantity' => 5,
                'defect_category_id' => $defFabric->id,
            ],
            [
                'client_id' => $clientMerdeka->id,
                'inspected_by' => $qcInspector->id,
                'inspected_at' => now()->subHours(3),
                'notes' => 'Kain bolong terkena gesekan mesin jahit parah, tidak bisa diperbaiki.',
            ]
        );

        WasteRecord::query()->firstOrCreate(
            [
                'order_id' => $order2->id,
                'client_id' => $clientMerdeka->id,
                'category' => $defFabric->name,
                'quantity' => 5,
            ],
            [
                'uom' => 'pcs',
                'recorded_by' => $qcInspector->id,
                'notes' => 'Afkir inspeksi QC #'.$qcScrap->id,
            ]
        );

        // Batch perakitan kedua: 15 pcs siap QC
        Assembly::query()->firstOrCreate(
            ['order_id' => $order2->id, 'quantity' => 15],
            [
                'client_id' => $clientMerdeka->id,
                'assembled_by' => $tailor->id,
                'assembled_at' => now()->subMinutes(45),
                'notes' => 'Batch 2 Jaket Parka - Baru disetor',
            ]
        );

        // 6. ORDER 3: Belum Ada Barang Perakitan (Status: Menunggu Perakitan)
        Order::query()->firstOrCreate(
            ['order_number' => 'ORD-QC-DEMO-03'],
            [
                'client_id' => $clientGlobal->id,
                'work_scheme' => WorkScheme::CMT,
                'quantity' => 50,
                'status' => OrderStatus::IN_PRODUCTION,
                'due_date' => now()->addWeeks(3),
                'notes' => 'Celana Chino - Masih dalam proses jahit line 1, belum ada perakitan disetor.',
            ]
        );

        // 7. ORDER 4: Selesai Penuh QC (100% Lolos)
        $order4 = Order::query()->firstOrCreate(
            ['order_number' => 'ORD-QC-DEMO-04'],
            [
                'client_id' => $clientMaklon->id,
                'work_scheme' => WorkScheme::CMT,
                'quantity' => 50,
                'status' => OrderStatus::IN_PRODUCTION,
                'due_date' => now()->addDays(5),
                'notes' => 'Kaos Polo - Seluruh batch telah lolos QC 100%.',
            ]
        );

        $assembly4 = Assembly::query()->firstOrCreate(
            ['order_id' => $order4->id, 'quantity' => 50],
            [
                'client_id' => $clientMaklon->id,
                'assembled_by' => $tailor->id,
                'assembled_at' => now()->subDays(2),
                'notes' => 'Full batch Kaos Polo',
            ]
        );

        QcRecord::query()->firstOrCreate(
            [
                'order_id' => $order4->id,
                'assembly_id' => $assembly4->id,
                'result' => QcResult::PASS,
                'quantity' => 50,
            ],
            [
                'client_id' => $clientMaklon->id,
                'inspected_by' => $qcInspector->id,
                'inspected_at' => now()->subDay(),
                'notes' => 'Kualitas prima, 50 pcs lolos ke Finished Goods.',
            ]
        );
    }
}
