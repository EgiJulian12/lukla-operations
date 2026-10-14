<?php

namespace Database\Seeders;

use App\Enums\ClientType;
use App\Enums\OrderStatus;
use App\Enums\RateCardStatus;
use App\Enums\RateType;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Enums\WorkScheme;
use App\Models\Article;
use App\Models\Client;
use App\Models\Component;
use App\Models\Order;
use App\Models\RateCard;
use App\Models\Sample;
use App\Models\SampleRevision;
use App\Models\TimeStudy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ApprovedSampleDemoSeeder extends Seeder
{
    /**
     * Menghasilkan data sample yang telah disetujui (Approved) lengkap dengan:
     * - Rincian komponen bertahap (sequence)
     * - Hasil pengujian durasi time study (SMV)
     * - Tarif borongan (Rate Card) dengan berbagai status approval
     * - Order produksi massal untuk skema CMT, Semi-CMT, dan CMT+Bahan
     */
    public function run(): void
    {
        // 1. Dapatkan atau buat pengguna yang berwenang
        $timSample = User::query()->firstOrCreate(
            ['email' => 'tim_sample@lukla.test'],
            [
                'name' => 'Tim Sample / Adis Demo',
                'password' => bcrypt('password'),
            ]
        );
        if (! $timSample->hasRole(UserRole::SAMPLE_TEAM->value)) {
            $timSample->assignRole(UserRole::SAMPLE_TEAM->value);
        }

        $prodLead = User::query()->firstOrCreate(
            ['email' => 'kepala_produksi@lukla.test'],
            [
                'name' => 'Kepala Produksi / Mandor Demo',
                'password' => bcrypt('password'),
            ]
        );
        if (! $prodLead->hasRole(UserRole::PRODUCTION_LEAD->value)) {
            $prodLead->assignRole(UserRole::PRODUCTION_LEAD->value);
        }

        $owner = User::query()->firstOrCreate(
            ['email' => 'owner_eksekutif@lukla.test'],
            [
                'name' => 'Owner Eksekutif Demo',
                'password' => bcrypt('password'),
            ]
        );
        if (! $owner->hasRole(UserRole::OWNER_EXECUTIVE->value)) {
            $owner->assignRole(UserRole::OWNER_EXECUTIVE->value);
        }

        // 2. Klien maklon
        $clientA = Client::query()->firstOrCreate(
            ['code' => 'CLI-NUSANTARA'],
            [
                'name' => 'PT Maklon Nusantara Tekstil',
                'type' => ClientType::MAKLON,
                'email' => 'operations@nusantara-tekstil.co.id',
                'phone' => '081234567890',
                'address' => 'Kawasan Industri Rancaekek, Bandung',
            ]
        );

        $clientB = Client::query()->firstOrCreate(
            ['code' => 'CLI-KREASI'],
            [
                'name' => 'CV Busana Kreasi Utama',
                'type' => ClientType::MAKLON,
                'email' => 'procurement@kreasi-busana.com',
                'phone' => '081987654321',
                'address' => 'Sentra Rajut Binong Jati, Bandung',
            ]
        );

        // 3. Kasus 1: Kemeja Oxford (Order Skema CMT)
        $this->seedSampleWithFlow(
            client: $clientA,
            articleName: 'Kemeja Oxford Long Sleeve',
            articleCode: 'ART-OXF-001',
            sampleTitle: 'Sample Kemeja Oxford Slim Fit v1.2',
            sampleCode: 'SMP-OXF-2026',
            workScheme: WorkScheme::CMT,
            componentsData: [
                ['name' => 'Kerah Baju & Daun Kerah', 'code' => 'KRH-01', 'sec' => 120, 'rate' => 2500, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Saku Depan & Jahit Bartack', 'code' => 'SKU-01', 'sec' => 90, 'rate' => 1200, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Pasang Lengan & Manset', 'code' => 'LNG-01', 'sec' => 160, 'rate' => 3000, 'status' => RateCardStatus::PENDING],
                ['name' => 'Jahit Sisi & Kelim Bawah', 'code' => 'BDN-01', 'sec' => 180, 'rate' => 3500, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Pasang Kancing & Lubang Kancing', 'code' => 'KCG-01', 'sec' => 75, 'rate' => 1000, 'status' => RateCardStatus::APPROVED],
            ],
            specs: [
                'Ukuran' => 'Reguler L (Lebar Dada 54 cm, Panjang 74 cm)',
                'Kerah' => 'Button Down Collar',
                'Jahitan' => 'Make-Up Jarum 2 (Double Needle) 1/4 inch',
                'Kancing' => 'Polyester 4 Lubang 18L',
            ],
            materialUsage: [
                'Kain Katun Oxford' => '1.65 meter / pcs',
                'Interlining Kerah' => '0.15 meter / pcs',
                'Kancing Baju' => '8 butir / pcs',
                'Benang Jahit' => '120 meter / pcs',
            ],
            orderNumber: 'ORD-CMT-2026-001',
            orderQty: 600,
            approver: $timSample,
        );

        // 4. Kasus 2: Polo Shirt Pique (Order Skema Semi-CMT)
        $this->seedSampleWithFlow(
            client: $clientB,
            articleName: 'Kaos Polo Pique CVC 24s',
            articleCode: 'ART-POLO-002',
            sampleTitle: 'Sample Kaos Polo Bordir Dada',
            sampleCode: 'SMP-POLO-2026',
            workScheme: WorkScheme::SEMI_CMT,
            componentsData: [
                ['name' => 'Kerah Rajut & Placket Depan', 'code' => 'KRH-PLQ', 'sec' => 140, 'rate' => 2200, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Bordir Logo Dada Kiri', 'code' => 'BRD-01', 'sec' => 45, 'rate' => 1500, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Pasang Rib Manset Lengan', 'code' => 'RIB-01', 'sec' => 80, 'rate' => 1300, 'status' => RateCardStatus::PENDING],
                ['name' => 'Obras Badan Samping & Belah Bawah', 'code' => 'BDN-SAMP', 'sec' => 110, 'rate' => 2000, 'status' => RateCardStatus::APPROVED],
            ],
            specs: [
                'Ukuran' => 'All Size M-L',
                'Kain' => 'Pique CVC Gramasi 210 GSM',
                'Rib' => 'Rib Katun Spandeks 1x1 matching color',
            ],
            materialUsage: [
                'Bahan Pique CVC' => '0.45 kg / pcs',
                'Kerah & Manset Rajut' => '1 set / pcs',
                'Kancing Polo 2 Lubang' => '3 butir / pcs',
            ],
            orderNumber: 'ORD-SCMT-2026-002',
            orderQty: 1200,
            approver: $owner,
        );

        // 5. Kasus 3: Jaket Windbreaker (Order Skema CMT + Bahan / Full Package)
        $this->seedSampleWithFlow(
            client: $clientA,
            articleName: 'Jaket Windbreaker Hoodie Taslan',
            articleCode: 'ART-JKT-003',
            sampleTitle: 'Sample Jaket Outdoor Water-Repellent',
            sampleCode: 'SMP-JKT-2026',
            workScheme: WorkScheme::FULL_PACKAGE,
            componentsData: [
                ['name' => 'Jahit Kupluk / Hoodie & Tali Stopper', 'code' => 'HOD-01', 'sec' => 150, 'rate' => 2800, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Pasang Ritsleting Gigi Besi Depan', 'code' => 'ZIP-01', 'sec' => 180, 'rate' => 3500, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Pasang Furing Mesh & Saku Dalam', 'code' => 'FUR-01', 'sec' => 210, 'rate' => 4000, 'status' => RateCardStatus::PENDING],
                ['name' => 'Kelim Bawah & Karet Pinggang', 'code' => 'KLM-01', 'sec' => 95, 'rate' => 1800, 'status' => RateCardStatus::APPROVED],
            ],
            specs: [
                'Kain Utama' => 'Micro Taslan Waterproof 100% Nylon',
                'Furing' => 'Jaring Mesh Breathable',
                'Ritsleting' => 'YKK Vislon No. 5 Reverse',
            ],
            materialUsage: [
                'Kain Taslan' => '2.10 meter / pcs',
                'Furing Jaring' => '1.80 meter / pcs',
                'Ritsleting Utama' => '1 pcs (65 cm)',
                'Tali Kur & Stopper' => '1 set',
            ],
            orderNumber: 'ORD-PKG-2026-003',
            orderQty: 450,
            approver: $timSample,
        );

        // 6. Kasus 4: Celana Chino (Sample Approved yang belum dikonversi ke Order — untuk diuji coba aksi "Jadikan Order")
        $this->seedSampleWithFlow(
            client: $clientB,
            articleName: 'Celana Chino Stretch Kasual',
            articleCode: 'ART-CHN-004',
            sampleTitle: 'Sample Celana Chino Beige Reguler',
            sampleCode: 'SMP-CHN-2026',
            workScheme: null, // Tanpa order, siap di-convert lewat tombol di halaman detail
            componentsData: [
                ['name' => 'Saku Depan Miring (Slanted Pocket)', 'code' => 'SKU-DPN', 'sec' => 130, 'rate' => 2400, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Saku Belakang Bobok (Paspol)', 'code' => 'SKU-BLG', 'sec' => 160, 'rate' => 3200, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Pasang Golbi & Resleting', 'code' => 'GLB-01', 'sec' => 110, 'rate' => 2000, 'status' => RateCardStatus::APPROVED],
                ['name' => 'Ban Pinggang & Tempat Ikat Pinggang', 'code' => 'BAN-01', 'sec' => 170, 'rate' => 3000, 'status' => RateCardStatus::PENDING],
                ['name' => 'Jahit Pesak & Kelim Kaki', 'code' => 'PSK-01', 'sec' => 140, 'rate' => 2500, 'status' => RateCardStatus::APPROVED],
            ],
            specs: [
                'Ukuran' => 'Size 32 (Lingkar Pinggang 84 cm, Panjang 102 cm)',
                'Bahan' => 'Katun Twill Stretch 20/10 98% Cotton 2% Elastane',
                'Kancing Pinggang' => 'Metal Button 24L Cor Logam',
            ],
            materialUsage: [
                'Kain Twill Stretch' => '1.40 meter / pcs',
                'Kain Kantong Asahi' => '0.35 meter / pcs',
                'Ritsleting Celana' => 'YKK Metal 15 cm',
            ],
            orderNumber: null,
            orderQty: 0,
            approver: $timSample,
        );
    }

    /**
     * Helper untuk membuat data artikel, komponen, sample, revisi, time study, rate card, dan order.
     */
    private function seedSampleWithFlow(
        Client $client,
        string $articleName,
        string $articleCode,
        string $sampleTitle,
        string $sampleCode,
        ?WorkScheme $workScheme,
        array $componentsData,
        array $specs,
        array $materialUsage,
        ?string $orderNumber,
        int $orderQty,
        User $approver,
    ): void {
        $article = Article::query()->firstOrCreate(
            ['client_id' => $client->id, 'code' => $articleCode],
            [
                'name' => $articleName,
                'description' => 'Artikel standar produksi '.$articleName,
            ]
        );

        $sample = Sample::query()->firstOrCreate(
            ['client_id' => $client->id, 'code' => $sampleCode],
            [
                'article_id' => $article->id,
                'title' => $sampleTitle,
                'status' => SampleStatus::APPROVED,
                'notes' => 'Sample disetujui untuk acuan produksi massal sesuai spesifikasi revisi v1.',
            ]
        );

        // Buat revisi yang disetujui (Approved Revision)
        $revision = SampleRevision::query()->firstOrCreate(
            ['sample_id' => $sample->id, 'version' => 1],
            [
                'client_id' => $client->id,
                'specs' => $specs,
                'material_usage' => $materialUsage,
                'status' => SampleStatus::APPROVED,
                'notes' => 'Toleransi ukuran +/- 1 cm. Finishing rapi dan setrika uap.',
                'created_by' => $approver->id,
                'approved_by' => $approver->id,
                'approved_at' => Carbon::now()->subDays(3),
            ]
        );

        $sample->update([
            'status' => SampleStatus::APPROVED,
            'current_revision_id' => $revision->id,
            'approved_revision_id' => $revision->id,
        ]);

        // Buat komponen, time study, dan rate card
        foreach ($componentsData as $idx => $compInfo) {
            $component = Component::query()->firstOrCreate(
                ['article_id' => $article->id, 'code' => $compInfo['code']],
                [
                    'name' => $compInfo['name'],
                    'sequence' => $idx + 1,
                ]
            );

            // Time Study
            TimeStudy::query()->updateOrCreate(
                [
                    'sample_revision_id' => $revision->id,
                    'component_id' => $component->id,
                ],
                [
                    'client_id' => $client->id,
                    'duration_seconds' => $compInfo['sec'],
                    'recorded_by' => $approver->id,
                    'recorded_at' => Carbon::now()->subDays(4),
                    'notes' => 'Uji coba operator standar tingkat kemahiran menengah.',
                ]
            );

            // Rate Card
            RateCard::query()->updateOrCreate(
                [
                    'client_id' => $client->id,
                    'article_id' => $article->id,
                    'component_id' => $component->id,
                    'rate_type' => RateType::SEWING,
                ],
                [
                    'rate' => $compInfo['rate'],
                    'status' => $compInfo['status'],
                    'effective_from' => Carbon::now()->subDays(10)->toDateString(),
                    'approved_by' => $compInfo['status'] === RateCardStatus::APPROVED ? $approver->id : null,
                    'approved_at' => $compInfo['status'] === RateCardStatus::APPROVED ? Carbon::now()->subDays(2) : null,
                    'notes' => 'Tarif per potong hasil kesepakatan produksi.',
                ]
            );
        }

        // Jika skema diberikan, buat Order produksi massal terkait
        if ($workScheme !== null && filled($orderNumber)) {
            Order::query()->firstOrCreate(
                ['order_number' => $orderNumber],
                [
                    'client_id' => $client->id,
                    'article_id' => $article->id,
                    'sample_id' => $sample->id,
                    'sample_revision_id' => $revision->id,
                    'work_scheme' => $workScheme,
                    'quantity' => $orderQty,
                    'status' => OrderStatus::CONFIRMED,
                    'due_date' => Carbon::now()->addDays(20)->toDateString(),
                    'notes' => 'Dikonversi dari '.$sampleTitle.' (Skema: '.$workScheme->getLabel().')',
                    'created_by' => $approver->id,
                ]
            );
        }
    }
}
