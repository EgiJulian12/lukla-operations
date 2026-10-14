# Lukla Operations Engine - Parallel Execution & Task Breakdown

> **Panduan untuk Tim / AI Subagent Parallel**:
> Dokumen ini dirancang agar tugas dapat **dikerjakan secara paralel dan independen** oleh beberapa developer / AI model sekaligus tanpa saling memblokir (*non-blocking*).
> 
> - **Sumber Kebenaran Bisnis (Source of Truth)**: Diselaraskan ketat dengan `docs/Business_requirement_summary.md` dan `docs/lukla-operations-engine.md`.
> - Semua aturan spekulatif (seperti retensi 7 tahun, batas arbitrer karakter 10-500, batas 3x percobaan, batas 1-100 pcs) telah dibersihkan agar murni mencerminkan kebutuhan bisnis nyata.
> - Setiap modul dilengkapi kontrak data mock mandiri dan perintah verifikasi test.

---

## 🗺️ Peta Dependensi & Orkestrasi Paralel (Execution Matrix)

```
[TRACK A: FINANCE & RATE] ────────> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 1: RateManager, Margin Formula & Rate Approval (BR 4.1, FR-010)

[TRACK B: PRODUCTION & FLOOR] ────> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 2: Output Recording Interval 30 Menit (FR-011, BR 4.5)
  ├─ Modul 3: Verification Workflow & Resubmission (FR-012, BR 4.5)
  └─ Modul 4: Station Rotation & Dynamic Line Allocation (FR-009, NFR-001)

[TRACK C: QUALITY & REPAIR] ──────> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 5: Repair Domain (Model, Migration, Service) (FR-014, BR 4.6)
  └─ Modul 6: QC Inspection Transition & Waste Hook (FR-008, FR-014, BR 4.6)

[TRACK D: WAREHOUSE & INVENTORY] ─> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 7: Inventory State Tracking & Waste Recording (FR-005, FR-008)
  └─ Modul 7B: Client & Order Isolation Guard & Semi-CMT Tagging (FR-006, FR-007, BR 4.4)

[TRACK E: PAYROLL & SETTLEMENT] ──> [Bisa Paralel menggunakan Contract / Mock Data]
  ├─ Modul 8: QC Bonus Pool 3-Way Distribution (FR-017, BR 4.3)
  └─ Modul 9: Weekly Payroll Saturday Close & Meal Allowance (FR-015, FR-016, BR 4.2)

[TRACK F: SYSTEM ORCHESTRATION & LIFECYCLE] ──> [Dikerjakan Terakhir setelah Track A-E Selesai]
  ├─ Modul 10A: Order Lifecycle & Sample-to-Order Conversion (FR-001, FR-003, FR-004)
  └─ Modul 10B: Saturday Close Scheduler & End-to-End Regression Test
```

### Tabel Status Ketergantungan (Parallel Readiness)

| Modul | Track | Reqs Bisnis | Dependensi Riil | Cara Eksekusi Paralel Tanpa Menunggu | Status Paralel |
|---|---|---|---|---|---|
| **Modul 1: Rate & Margin** | A | BR 4.1, FR-010 | Tidak ada | Beroperasi pada kalkulasi formula margin & `RateCard`. | ✅ **Selesai (Completed)** |
| **Modul 2: Output Recording** | B | FR-011, BR 4.5 | Tidak ada | Menggunakan factory `Order`, `Component`, `User`. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 3: Verification & Resubmit** | B | FR-012, BR 4.5 | Kolom penolakan di `output_entries` | Cukup buat migrasi kolomnya sendiri. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 4: Line Allocation** | B | FR-009, NFR-001 | Kolom stasiun di `line_assignments` | Independen dari output recording. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 5: Repair Domain** | C | FR-014, BR 4.6 | Relasi ke `QcRecord` (sudah ada di DB) | Buat tabel `repairs` & `RepairManager`. | ✅ **Selesai (Completed)** |
| **Modul 6: QC Inspection Hook** | C | FR-008, FR-014 | Memanggil Modul 5 jika REPAIR | Gunakan Mock / Null Object jika Modul 5 belum merge. | ✅ **Selesai (Completed)** |
| **Modul 7: Inventory & Waste**| D | FR-005, FR-008 | Tidak ada | Beroperasi pada `InventoryItem` & `WasteRecord`. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 7B: Client Isolation** | D | FR-006, FR-007, BR 4.4 | Tidak ada | Verifikasi `BelongsToClient` & Tagging Semi-CMT. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 8: QC Bonus Pool** | E | FR-017, BR 4.3 | Membaca `QcRecord` berstatus PASS | Gunakan factory `QcRecord::factory()->create(['result' => 'PASS'])`. | ✅ **Selesai (Completed)** |
| **Modul 9: Weekly Payroll** | E | FR-015, FR-016, BR 4.2 | Membaca output VERIFIED & rate | Gunakan factory `OutputEntry::factory()->verified()`. | ✅ **Selesai (Completed)** |
| **Modul 10A: Order Lifecycle** | F | FR-001, FR-004 | Model `Order`, `Sample` | Verifikasi lifecycle state machine & convert sample. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 10B: Scheduler & E2E** | F | Semua | Menghubungkan Modul 8 & 9 | Menunggu Track B, C, E selesai. | ⏳ **Tahap Akhir (Integrasi)** |

---

## 📦 KONTRAK DATA MOCK (Untuk Pengerjaan Paralel)

Jika Anda mengerjakan modul yang membutuhkan data dari modul lain yang sedang dikerjakan orang lain, gunakan kontrak data factory berikut di unit/feature test Anda:

### Kontrak 1: Untuk Pengerjaan QC Bonus & Inspection (Track C & E)
```php
// Tidak perlu menunggu modul lain: buat record QC langsung via Eloquent
$qcPass = App\Models\QcRecord::create([
    'client_id' => $clientId,
    'order_id' => $orderId,
    'result' => App\Enums\QcResult::PASS,
    'quantity' => 10,
    'inspected_at' => now(),
]);
```

### Kontrak 2: Untuk Pengerjaan Payroll Calculation (Track E)
```php
// Mock Output Terverifikasi untuk test perhitungan payroll:
$outputVerified = App\Models\OutputEntry::create([
    'client_id' => $clientId,
    'order_id' => $orderId,
    'production_line_id' => $lineId,
    'user_id' => $operatorId,
    'component_id' => $componentId,
    'quantity' => 20,
    'interval_start' => now()->startOfWeek()->addHours(8),
    'interval_end' => now()->startOfWeek()->addHours(8)->addMinutes(30),
    'status' => App\Enums\OutputStatus::VERIFIED,
    'verified_by' => $supervisorId,
    'verified_at' => now(),
]);
```

---

# DETAIL RINCIAN TASK PARALEL

---

## TRACK A: FINANCE & RATE MANAGEMENT (Independen)

### MODUL 1: RateManager, Formula Margin & Rate Approval (BR 4.1, FR-010)
*PIC / Agent: Worker A*  
*Dependensi: None (Mandiri)*  
*Dasar Bisnis: BR 4.1 Formula `RATE ORDER = RATE KOMPONEN + MATERIAL + OVERHEAD + PROFIT`, FR-010, FR-026*

- [x] **1.1** Buat Service `App\Services\Finance\RateManager`
  - **File Target**: `app/Services/Finance/RateManager.php`
  - **Spesifikasi Logika**:
    - Method `computeAvailableMargin(float $sellingPrice, float $materialCost, float $overhead, float $profit): float`
      - Rumus: `$sellingPrice - ($materialCost + $overhead + $profit)`.
      - Validasi: Jika hasil < 0, lemparkan `App\Exceptions\InvalidRateMarginException` ("Total komponen biaya melebihi harga jual order").
    - Method `validateRateKomponen(float $componentRate, float $availableMargin): bool`
      - Validasi: `Rate Komponen <= Available Margin`.
    - Method `validateTotalComponentsMargin(array $componentRates, float $availableMargin): bool`
      - Validasi: `SUM(Rate Komponen) <= Available Margin`.
    - Method `approveRateKomponen(Order $order, Component $component, User $approver): void`
      - Kepala Produksi menentukan Rate Komponen, validasi terhadap batas margin yang ditetapkan Owner.
      - Rekam pengguna penyetuju (`approver_id`) dan timestamp persetujuan (FR-026).
- [x] **1.2** Pencatatan Audit Perubahan Tarif
  - **File Target**: `app/Models/RateCard.php`
  - **Spesifikasi**: Rekam jejak audit (actor, timestamp, perubahan nilai) setiap kali terjadi create/update pada rate card (FR-022).
- [x] **1.3** Unit & Feature Test Mandiri
  - **File Target**: `tests/Feature/Finance/RateManagerTest.php`
  - **Command Verifikasi**: `php artisan test --filter=RateManagerTest`

---

## TRACK B: PRODUCTION FLOOR WORKFLOW (Independen)

### MODUL 2: Output Recording Interval 30 Menit & Penugasan Operator (FR-011, BR 4.5)
*PIC / Agent: Worker B1*  
*Dependensi: None (Mandiri)*  
*Dasar Bisnis: BR 4.5, BR-004, FR-011 Output capture 30 menit per penjahit per station*

- [ ] **2.1** Validasi Penugasan & Pencatatan Output di `ProductionService`
  - **File Target**: `app/Services/Production/ProductionService.php` (method `submitOutput`)
  - **Spesifikasi Logika**:
    - Penjahit menginput output per 30 menit per station (BR 4.5).
    - Validasi kuantitas minimal 1 unit.
    - Hitung interval boundary 30 menit (:00 s/d :30 atau :30 s/d :00) dan catat interval_start & interval_end.
    - Validasi Penugasan: Operator harus memiliki alokasi aktif di Line terkait (`unassigned_at IS NULL`).
    - Output baru diinisialisasi dengan status `SUBMITTED` dan belum layak masuk payroll (FR-011, FR-012).
- [ ] **2.2** Method Helper Interval Boundary
  - **File Target**: `app/Services/Production/ProductionService.php`
  - **Spesifikasi**: Public method `calculateInterval(DateTimeInterface $time): array{0: Carbon, 1: Carbon}`.
- [ ] **2.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Production/OutputRecordingRulesTest.php`
  - **Command Verifikasi**: `php artisan test --filter=OutputRecordingRulesTest`

---

### MODUL 3: Output Verification Workflow & Resubmission (FR-012, BR 4.5)
*PIC / Agent: Worker B2*  
*Dependensi: Menambahkan kolom pada tabel `output_entries`*  
*Dasar Bisnis: BR 4.5, FR-012 Supervisor verify/reject, penjahit bisa resubmit jika reject*

- [ ] **3.1** Migrasi Kolom Penolakan & Alasan
  - **File Target**: Migration baru `database/migrations/xxxx_add_rejection_fields_to_output_entries_table.php`
  - **Spesifikasi**:
    - Tambahkan kolom: `rejection_reason` (text, nullable), `rejected_by` (foreignId nullOnDelete).
- [ ] **3.2** Model Update `OutputEntry`
  - **File Target**: `app/Models/OutputEntry.php`
  - **Spesifikasi**: Daftarkan kolom baru ke `$fillable`, relasi `rejectedBy(): BelongsTo`.
- [ ] **3.3** Enhancement Method Verifikasi, Penolakan, & Resubmission
  - **File Target**: `app/Services/Production/ProductionService.php`
  - **Spesifikasi Logika**:
    - Otorisasi Verifikasi: Hanya Kepala Produksi atau Mandor (`PRODUCTION_LEAD`) yang berhak memverifikasi/menolak (FR-012, FR-021). Operator dilarang memverifikasi output sendiri.
    - Pada `verifyOutput(OutputEntry $entry, User $verifier)`:
      - Ubah status ke `VERIFIED`, catat `verified_by`, `verified_at`, kunci record agar tidak dapat diedit lagi oleh penjahit, dan tandai eligible untuk payroll (FR-012, FR-021).
    - Pada `rejectOutput(OutputEntry $entry, string $reason)`:
      - Simpan `rejection_reason`, `rejected_by = auth()->id()`, ubah status ke `REJECTED`.
    - Pada `resubmitOutput(OutputEntry $entry, int $newQuantity, ?string $notes = null): OutputEntry`:
      - Jika output berstatus `REJECTED`, penjahit dapat mengoreksi dan resubmit kembali ke status `SUBMITTED` (BR 4.5).
- [ ] **3.4** Unit & Feature Test
  - **File Target**: `tests/Feature/Production/OutputResubmissionTest.php`
  - **Command Verifikasi**: `php artisan test --filter=OutputResubmissionTest`

---

### MODUL 4: Dynamic Line Allocation & Station Rotation (FR-009, NFR-001)
*PIC / Agent: Worker B3*  
*Dependensi: None (Bisa paralel dengan Modul 2 & 3)*  
*Dasar Bisnis: BR-003, FR-009, NFR-001 Alokasi dinamis ±70 mesin tanpa batas slot kaku, rotasi line/station*

- [ ] **4.1** Migrasi Kolom Stasiun pada `line_assignments`
  - **File Target**: Migration baru `database/migrations/xxxx_add_station_to_line_assignments_table.php`
  - **Spesifikasi**: Tambahkan kolom `station_number` (string, nullable).
- [ ] **4.2** Service `LineAllocationService`
  - **File Target**: `app/Services/Production/LineAllocationService.php`
  - **Spesifikasi Logika**:
    - Method `assignOperator(ProductionLine $line, User $operator, ?string $station = null): LineAssignment`:
      - Tutup penugasan aktif operator sebelumnya saat dirotasi ke line lain (`unassigned_at = now()`).
      - Buat penugasan baru dengan `assigned_at = now()`.
      - Kapasitas line bersifat cair tanpa batas slot kaku (NFR-001).
    - Method `rotateStation(LineAssignment $assignment, string $newStation): LineAssignment`.
    - Riwayat alokasi ditutup lewat `unassigned_at` (tidak di-hard delete) agar riwayat tetap dapat diaudit (FR-022).
- [ ] **4.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Production/LineAllocationServiceTest.php`
  - **Command Verifikasi**: `php artisan test --filter=LineAllocationServiceTest`

---

## TRACK C: QUALITY ASSURANCE & REPAIR WORKFLOW (Independen)

### MODUL 5: Repair Domain (Model, Migration, Service) (FR-014, BR 4.6)
*PIC / Agent: Worker C1*  
*Dependensi: None (Mandiri - Menggunakan tabel `qc_records` yang sudah ada)*  
*Dasar Bisnis: BR 4.6 Hasil QC REPAIR: ke repair area, kembali produksi, QC ulang*

- [x] **5.1** Buat Enum `App\Enums\RepairStatus`
  - **File Target**: `app/Enums/RepairStatus.php`
  - **Values**: `PENDING`, `IN_REPAIR`, `COMPLETED`.
- [x] **5.2** Migrasi Tabel `repairs`
  - **File Target**: Migration baru `database/migrations/xxxx_create_repairs_table.php`
  - **Kolom**: `id`, `qc_record_id` (foreignId ke `qc_records`), `assigned_repairer_id` (foreignId ke `users`, nullable), `status` (string), `completed_at` (timestamp nullable), `notes` (text nullable), `timestamps`.
- [x] **5.3** Model `App\Models\Repair`
  - **File Target**: `app/Models/Repair.php`
  - **Relasi & Helper**: `belongsTo(QcRecord::class)`, `belongsTo(User::class, 'assigned_repairer_id')`.
- [x] **5.4** Service `RepairManager`
  - **File Target**: `app/Services/Quality/RepairManager.php`
  - **Spesifikasi Logika**:
    - `createFromQc(QcRecord $qc, ?User $repairer = null): Repair` (status `PENDING`).
    - `markCompleted(Repair $repair): Repair` (set status `COMPLETED`, `completed_at = now()`, unit kembali ke antrean produksi/QC ulang).
- [x] **5.5** Unit & Feature Test
  - **File Target**: `tests/Feature/Quality/RepairManagerTest.php`
  - **Command Verifikasi**: `php artisan test --filter=RepairManagerTest`

---

### MODUL 6: QC Inspection Transition, Bonus Counter & Waste Hook (FR-008, FR-014, BR 4.6)
*PIC / Agent: Worker C2*  
*Dependensi Interface: Memanggil `RepairManager` jika status REPAIR (atau fallback graceful jika Modul 5 belum di-merge)*  
*Dasar Bisnis: BR 4.6 3 Hasil QC (PASS, REPAIR, SCRAP), wajib input defect category jika REPAIR/SCRAP, +1 bonus per PASS*

- [x] **6.1** Validasi Defect Category & Hook Status pada `QcService`
  - **File Target**: `app/Services/Quality/QcService.php`
  - **Spesifikasi Logika**:
    - Unit yang diinspeksi berasal dari perakitan/komponen terverifikasi (FR-013).
    - Jika hasil `PASS`:
      - Ke Finished Goods.
      - Terhitung +1 unit ke QC Bonus Pool periode berjalan (BR 4.6, FR-017).
    - Jika hasil `REPAIR`:
      - Wajib input `defect_category` (tolak jika kosong, BR 4.6, FR-014).
      - Unit diarahkan ke repair area dan tiket repair dibuat via `RepairManager`.
      - Tidak menambah bonus pool QC.
    - Jika hasil `SCRAP`:
      - Wajib input `defect_category` (tolak jika kosong, BR 4.6, FR-014).
      - Unit diarahkan ke scrap area dan otomatis tercatat ke `waste_records` dengan kategori dan atribusi client/order (FR-008).
      - Tidak menambah bonus pool QC.
- [x] **6.2** Unit & Feature Test
  - **File Target**: `tests/Feature/Quality/QcInspectionFlowTest.php`
  - **Command Verifikasi**: `php artisan test --filter=QcInspectionFlowTest`

---

## TRACK D: WAREHOUSE & INVENTORY (Independen)

### MODUL 7: Inventory State Tracking & Waste Recording (FR-005, FR-008)
*PIC / Agent: Worker D1*  
*Dependensi: None (Mandiri)*  
*Dasar Bisnis: BR-002, FR-005, FR-008, State Machine Inventory: Received → Issued → Cut → Consumed*

- [ ] **7.1** Validasi Siklus Hidup Inventory di `InventoryService`
  - **File Target**: `app/Services/Inventory/InventoryService.php`
  - **Spesifikasi Logika**:
    - Setiap item inventory wajib memiliki `client_id` dan `order_id` (BR 4.4, FR-005).
    - Siklus status inventory: Material mentah diterima (`RAW` / Received), dikeluarkan ke cutting (`WIP` / Issued/Cut), dan dikonsumsi ke perakitan (`FINISHED` / Consumed).
    - Transisi ke WIP dan FINISHED wajib membawa konteks `order_id` yang valid.
- [ ] **7.2** Pencatatan Waste & Scrap Material
  - **File Target**: `app/Services/Inventory/InventoryService.php`
  - **Spesifikasi Logika**:
    - Catat potongan bahan sisa / scrap dengan kategori waste, kuantitas, dan atribusi client/order (FR-008).
- [ ] **7.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Inventory/InventorySequenceAndWasteTest.php`
  - **Command Verifikasi**: `php artisan test --filter=InventorySequenceAndWasteTest`

---

### MODUL 7B: Client & Order Isolation Guard & Semi-CMT Tagging (FR-006, FR-007, BR 4.4)
*PIC / Agent: Worker D2*  
*Dependensi: None (Mandiri)*  
*Dasar Bisnis: BR 2.1 Skema Maklon (CMT, Semi-CMT, Full Package), BR 4.4 Isolasi Stok, FR-006, FR-007*

- [ ] **7.4** Validasi Isolasi Material & Aksesoris Semi-CMT
  - **File Target**: `app/Services/Inventory/InventoryService.php`
  - **Spesifikasi Logika**:
    - Tiga skema maklon (BR 2.1):
      - **CMT**: Client pemilik seluruh material.
      - **Semi-CMT**: Client pemilik bahan utama, Lukla penyedia aksesoris. Aksesoris dari Lukla WAJIB di-tag dengan `client_id` klien tujuan (BR 4.4).
      - **Full Package**: Lukla pemilik seluruh material.
    - Aturan Isolasi Stok (BR 4.4, FR-007):
      - Sebelum mencatat issue/pengeluaran material, bandingkan `client_id` dan `order_id` material dengan order tujuan.
      - Jika `client_id` material ≠ `client_id` order tujuan: Sistem **WAJIB REJECT** dengan peringatan eksplisit (FR-007, Acceptance Scenario 1).
    - Audit Trail Movement: Setiap pergerakan (receive, issue, transfer, adjustment) wajib membawa `client_id`, `order_id`, jenis pergerakan, dan identitas aktor (FR-006, FR-022).
- [ ] **7.5** Unit & Feature Test
  - **File Target**: `tests/Feature/Inventory/CrossClientIssueRejectionTest.php`
  - **Command Verifikasi**: `php artisan test --filter=CrossClientIssueRejectionTest`

---

## TRACK E: PAYROLL & SETTLEMENT (Paralel via Mock Data)

### MODUL 8: QC Bonus Pool 3-Way Distribution (FR-017, BR 4.3)
*PIC / Agent: Worker E1*  
*Dependensi Data: `QcRecord` berstatus PASS (Gunakan Kontrak 1 untuk testing tanpa menunggu Track C)*  
*Dasar Bisnis: BR 4.3 Formula `QC Bonus Pool = Σ (Passed Units × QC Bonus Rate)`, Distribusi Pool ÷ 3 ke 2 Mandor + 1 Kepala Operasional, FR-017*

- [x] **8.1** Service `QcBonusDistributionService`
  - **File Target**: `app/Services/Payroll/QcBonusDistributionService.php`
  - **Spesifikasi Logika**:
    - Method `finalizeBonusPool(PayrollPeriod $period): QcBonusPool`:
      - Hitung seluruh unit yang lolos QC (PASS) lintas order dan lintas klien dalam rentang periode (BR 4.3, FR-017).
      - Total Pool = `Passed Units × QC Bonus Rate`.
    - Method `distributeBonus(QcBonusPool $pool): array`:
      - Distribusikan pool secara rata kepada 3 penerima: **2 Mandor** (`PRODUCTION_LEAD`) dan **1 Kepala Operasional** (`OWNER_EXECUTIVE`) (BR 4.3, FR-017).
      - Per recipient = `Pool ÷ 3`.
      - Gunakan pembagian rata dengan sisa pembulatan dibebankan ke penerima terakhir via `$pool->splitEqually()` agar total pembagian sama persis dengan total pool.
- [x] **8.2** Unit & Feature Test
  - **File Target**: `tests/Feature/Payroll/QcBonusDistributionServiceTest.php`
  - **Command Verifikasi**: `php artisan test --filter=QcBonusDistributionServiceTest`

---

### MODUL 9: Weekly Payroll Saturday Close & Meal Allowance (FR-015, FR-016, BR 4.2)
*PIC / Agent: Worker E2*  
*Dependensi Data: `OutputEntry` VERIFIED & `RateCard` (Gunakan Kontrak 2 untuk testing tanpa menunggu Track B)*  
*Dasar Bisnis: BR 3.1 & 4.2 Payroll mingguan ditutup setiap Sabtu, Base Pay formula, Meal Allowance 10-15%, Headcount dinamis (BR-006)*

- [x] **9.1** Method `closePeriod` & Penguncian Output di `PayrollService`
  - **File Target**: `app/Services/Payroll/PayrollService.php`
  - **Spesifikasi Logika**:
    - Periode payroll ditutup setiap Sabtu: kunci seluruh output berstatus `VERIFIED` agar tidak dapat diubah lagi (BR 3.1 step 10, FR-015).
    - Headcount Dinamis: Hitung payroll tanpa batas jumlah penjahit kaku; penjahit yang bergabung di tengah minggu tetap mendapat perhitungan borongan akurat (BR-006, Acceptance Scenario 2).
- [x] **9.2** Kalkulasi Base Pay, Uang Makan, dan Laporan Payroll
  - **File Target**: `app/Services/Payroll/PayrollService.php`
  - **Spesifikasi Formula**:
    - `Base Pay = Σ (Verified Output × Rate Komponen)` (BR 3.1 step 10, FR-015).
    - `Meal Allowance = Meal% × Base Pay` (Default 10% - 15%, diinput oleh Finance di awal periode produksi) (BR 4.2, FR-016).
    - Total per penjahit = `Base Pay + Meal Allowance`.
    - Buat rincian laporan payroll untuk ditinjau oleh Finance dan Owner (Owner dapat VIEW reports, RBAC Section 5).
- [x] **9.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Payroll/PayrollClosingAndCalculationTest.php`
  - **Command Verifikasi**: `php artisan test --filter=PayrollClosingAndCalculationTest`

---

## TRACK F: SYSTEM INTEGRATION & LIFECYCLE (Tahap Akhir)

### MODUL 10A: Order Lifecycle & Sample-to-Order Conversion (FR-001, FR-003, FR-004)
*PIC / Agent: Worker F1*  
*Dependensi: None (Bisa dieksekusi paralel atau integrasi)*  
*Dasar Bisnis: BR 3.1 Alur Sample → Approval → Conversion ke Order Massal, State Machine Order*

- [ ] **10.1** Verifikasi Siklus Sample ke Order Massal
  - **File Target**: `app/Services/Sample/SampleService.php`
  - **Spesifikasi Logika**:
    - Rekam sample, kepemilikan klien, daftar komponen, dan riwayat revisi bertingkat (FR-001, BR-005).
    - Rekam time study per komponen dengan timer digital yang terikat ke revisi sample (FR-002).
    - Sample direview dan diapprove oleh klien sebelum bisa dikonversi (FR-003).
    - Konversi sample yang disetujui menjadi order massal dengan mempertahankan revisi sample yang disetujui dan skema maklon yang dipilih (CMT, Semi-CMT, Full Package) (FR-004, Acceptance Scenario 4).
    - Cegah pembuatan order massal jika sample belum disetujui (FR-021).
- [ ] **10.2** State Machine Siklus Hidup Order
  - **File Target**: `app/Models/Order.php`
  - **Spesifikasi Logika**:
    - Siklus order (Section 6.1): `Pending → Confirmed → In Production → QC Phase → Completed → Closed`.
    - Order yang sudah selesai / Closed terkunci dari perubahan transaksi (FR-021).
- [ ] **10.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Sample/SampleConversionTest.php`
  - **Command Verifikasi**: `php artisan test --filter=SampleConversionTest`

---

### MODUL 10B: Saturday Close Scheduler & End-to-End Regression
*PIC / Agent: Lead / Integrator*  
*Prasyarat: Semua Track A, B, C, D, E, F1 telah selesai dan di-merge*

- [ ] **10.4** Artisan Command `payroll:close-weekly`
  - **File Target**: `app/Console/Commands/PayrollCloseWeeklyCommand.php`
  - **Spesifikasi**: Memanggil penutupan periode payroll mingguan dan finalisasi pool bonus QC setiap Sabtu (Section 6.2).
- [ ] **10.5** Konfigurasi Jadwal di `routes/console.php`
  - **File Target**: `routes/console.php`
  - **Spesifikasi**:
    ```php
    use Illuminate\Support\Facades\Schedule;
    Schedule::command('payroll:close-weekly')->weekly()->saturdays()->at('00:00')->timezone('Asia/Jakarta');
    ```
- [ ] **10.6** Code Styling & Full Regression Testing
  - Jalankan formatter: `vendor/bin/pint --dirty --format agent`
  - Jalankan seluruh test: `php artisan test` (Wajib 100% Passed mencakup seluruh Acceptance Test Scenarios 1–4).