# Lukla Operations Engine - Parallel Execution & Task Breakdown

> **Panduan untuk Tim / AI Subagent Parallel**:
> Dokumen ini dirancang agar tugas dapat **dikerjakan secara paralel dan independen** oleh beberapa developer / AI model sekaligus tanpa saling memblokir (*non-blocking*).
> 
> - **Dependency Matrix & Mock Data Contract** disediakan di setiap modul agar developer tidak perlu menunggu modul lain selesai.
> - Setiap task memiliki spesifikasi input/output, file target, dan skrip verifikasi otomatis.
> - Telah diaudit 100% terhadap seluruh **14 Requirements** dan **10 Correctness Properties** di `requirements.md`.

---

## 🗺️ Peta Dependensi & Orkestrasi Paralel (Execution Matrix)

```
[TRACK A: FINANCE & RATE] ────────> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 1: RateManager, Margin Guard & Approval Workflow (Req 2)

[TRACK B: PRODUCTION & FLOOR] ────> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 2: Output Recording & Allocation Guard (Req 3)
  ├─ Modul 3: Verification Workflow, Resubmission & Audit (Req 4)
  └─ Modul 4: Station Rotation, Line Tracking & History (Req 11)

[TRACK C: QUALITY & REPAIR] ──────> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 5: Repair Domain (Model, Migration, Service) (Req 13)
  └─ Modul 6: QC Inspection Transition, Bonus Counter & Waste Hook (Req 6)

[TRACK D: WAREHOUSE & INVENTORY] ─> [Bisa Paralel Langsung (0 Dependency)]
  ├─ Modul 7: Inventory Sequence & Waste Reporting (Req 12, 14)
  └─ Modul 7B: Client Isolation Verification & Accessories Tagging (Req 1, 5)

[TRACK E: PAYROLL & SETTLEMENT] ──> [Bisa Paralel menggunakan Contract / Mock Data]
  ├─ Modul 8: QC Bonus Pool Finalizer, Remainder & Recipient Alert (Req 7)
  └─ Modul 9: Weekly Payroll Period Close, Locking & Payables Report (Req 8)

[TRACK F: SYSTEM ORCHESTRATION & LIFECYCLE] ──> [Dikerjakan Terakhir setelah Track A-E Selesai]
  ├─ Modul 10A: Order Lifecycle Guard & Sample Conversion Verification (Req 9, 10)
  └─ Modul 10B: Scheduler & Full End-to-End Regression Test (Semua Req)
```

### Tabel Status Ketergantungan (Parallel Readiness)

| Modul | Track | Reqs Terkait | Dependensi Riil | Cara Eksekusi Paralel Tanpa Menunggu | Status Paralel |
|---|---|---|---|---|---|
| **Modul 1: Rate & Margin** | A | Req 2 | Tidak ada | Beroperasi pada kalkulasi margin & `RateCard`. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 2: Output Recording** | B | Req 3 | Tidak ada | Menggunakan factory `Order`, `Component`, `User`. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 3: Verification & Resubmit** | B | Req 4 | Migrasi kolom di `output_entries` | Cukup buat migrasi kolomnya sendiri. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 4: Line Allocation** | B | Req 11 | Migrasi kolom di `line_assignments` | Independen dari output recording. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 5: Repair Domain** | C | Req 13 | Relasi ke `QcRecord` (sudah ada di DB) | Buat tabel `repairs` & `RepairManager`. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 6: QC Inspection Hook** | C | Req 6 | Memanggil Modul 5 jika REPAIR | Gunakan Mock / Null Object jika Modul 5 belum merge. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 7: Inventory & Waste**| D | Req 12, 14 | Tidak ada | Beroperasi pada `InventoryItem` & `WasteRecord`. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 7B: Client Isolation** | D | Req 1, 5 | Tidak ada | Verifikasi `BelongsToClient` & Tagging Semi-CMT. | ✅ **Bisa Langsung Dikerjakan** |
| **Modul 8: QC Bonus Pool** | E | Req 7 | Membaca `QcRecord` berstatus PASS | Gunakan factory `QcRecord::factory()->create(['result' => 'PASS'])`. | ✅ **Bisa Langsung Dikerjakan (Mocked)** |
| **Modul 9: Weekly Payroll** | E | Req 8 | Membaca output VERIFIED & rate | Gunakan factory `OutputEntry::factory()->verified()`. | ✅ **Bisa Langsung Dikerjakan (Mocked)** |
| **Modul 10A: Order Lifecycle** | F | Req 9, 10 | Model `Order`, `Sample` | Verifikasi state machine immutability & conversion. | ✅ **Bisa Langsung Dikerjakan** |
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

### MODUL 1: RateManager, Available Margin Guard & Approval (Req 2)
*PIC / Agent: Worker A*  
*Dependensi: None (Mandiri)*  
*Cakupan Requirement: Req 2.1 - 2.14, Property 1*

- [ ] **1.1** Buat Service `App\Services\Finance\RateManager`
  - **File Target**: `app/Services/Finance/RateManager.php`
  - **Spesifikasi Logika**:
    - Method `computeAvailableMargin(float $sellingPrice, float $materialCost, float $overhead, float $profit): float`
      - Rumus: `$sellingPrice - ($materialCost + $overhead + $profit)`.
      - Validasi: Jika hasil <= 0, lemparkan `App\Exceptions\InvalidRateMarginException` ("Margin tersedia tidak boleh nol atau negatif"). (Req 2.2, 2.3)
    - Method `validateRateKomponen(float $componentRate, float $availableMargin): bool`
      - Validasi: Jika `$componentRate > $availableMargin`, lemparkan exception menunjukkan batas maksimum yang diizinkan. (Req 2.4, 2.5)
    - Method `validateTotalComponentsMargin(array $componentRates, float $availableMargin): bool`
      - Validasi: Jika `array_sum($componentRates) > $availableMargin`, lemparkan exception menunjukkan jumlah shortfall. (Req 2.6, 2.7)
    - Method `enforceSequentialApproval(Order $order, Component $component): void`
      - Memastikan komponen disetujui berurutan (urutan 1, lalu 2, dst.). (Req 2.8)
      - Transisi status komponen ke `ACTIVE` jika disetujui, atau `REJECTED` jika ditolak. (Req 2.9, 2.10)
      - Jika komponen yang telah disetujui diubah, wajibkan alur re-approval. (Req 2.11)
      - Ketika seluruh komponen disetujui, tandai Rate Order / Order sebagai `APPROVED`. (Req 2.12)
- [ ] **1.2** Pencatatan Audit Perubahan Tarif (Retensi 7 Tahun)
  - **File Target**: `app/Models/RateCard.php`
  - **Spesifikasi**: Gunakan trait `Auditable` atau buat record audit setiap kali tarif berubah dengan field: `actor_id`, `timestamp`, `old_value`, `new_value`. (Req 2.13, 2.14)
- [ ] **1.3** Unit & Feature Test Mandiri
  - **File Target**: `tests/Feature/Finance/RateManagerTest.php`
  - **Command Verifikasi**: `php artisan test --filter=RateManagerTest`

---

## TRACK B: PRODUCTION FLOOR WORKFLOW (Independen)

### MODUL 2: Output Recording Interval 30 Menit & Operator Guard (Req 3)
*PIC / Agent: Worker B1*  
*Dependensi: None (Mandiri)*  
*Cakupan Requirement: Req 3.1 - 3.7, Property 10*

- [ ] **2.1** Validasi Kuota & Operator Assignment di `ProductionService`
  - **File Target**: `app/Services/Production/ProductionService.php` (method `submitOutput`)
  - **Spesifikasi Logika**:
    - Validasi kuantitas integer antara 1 sampai 100 (`1 <= quantity <= 100`). Jika di luar rentang, lemparkan `InvalidArgumentException`. (Req 3.1)
    - Timestamp direkam dalam format UTC dengan presisi waktu. (Req 3.1)
    - Hitung interval: `floor(minutes / 30) * 30`. Validasi waktu submit berada dalam `[Interval_Start, Interval_End)`. Jika di luar interval, tolak dengan pesan error informatif (current time, range interval, next start). (Req 3.2, 3.3, 3.5)
    - Validasi Penugasan: Cek apakah `$operator` terdaftar aktif di `LineAssignment` untuk `$line` pada waktu `$intervalAt` (`unassigned_at IS NULL`). Jika tidak ada, tolak dengan `App\Exceptions\UnassignedOperatorException`. (Req 3.6)
    - Record output baru diinisialisasi dengan status `SUBMITTED` dan ditandai pending verifikasi. (Req 3.4, 3.7)
- [ ] **2.2** Method Helper Interval Boundary
  - **File Target**: `app/Services/Production/ProductionService.php`
  - **Spesifikasi**: Public method `calculateInterval(DateTimeInterface $time): array{0: Carbon, 1: Carbon}` untuk boundary :00 dan :30.
- [ ] **2.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Production/OutputRecordingRulesTest.php`
  - **Command Verifikasi**: `php artisan test --filter=OutputRecordingRulesTest`

---

### MODUL 3: Output Verification Workflow, Resubmission & Audit (Req 4)
*PIC / Agent: Worker B2*  
*Dependensi: Menambahkan kolom pada tabel `output_entries`*  
*Cakupan Requirement: Req 4.1 - 4.9, Property 5*

- [ ] **3.1** Migrasi Kolom Resubmission & Alasan Penolakan
  - **File Target**: Migration baru `database/migrations/xxxx_add_resubmission_fields_to_output_entries_table.php`
  - **Spesifikasi**:
    - Tambahkan kolom: `rejection_count` (unsignedSmallInteger, default 0), `rejection_reason` (text, nullable), `rejected_by` (foreignId nullOnDelete).
- [ ] **3.2** Model Update `OutputEntry`
  - **File Target**: `app/Models/OutputEntry.php`
  - **Spesifikasi**: Daftarkan kolom baru ke `$fillable`, relasi `rejectedBy(): BelongsTo`.
- [ ] **3.3** Enhancement Method Verifikasi, Penolakan, Resubmit & Audit
  - **File Target**: `app/Services/Production/ProductionService.php`
  - **Spesifikasi Logika**:
    - Otorisasi Verifikasi: Pastikan pemanggil memiliki role Mandor atau Kepala Produksi (`PRODUCTION_LEAD`). Tolak jika unauthorized dengan pesan permission denied. (Req 4.3)
    - Pada `verifyOutput(OutputEntry $entry, User $verifier)`:
      - Ubah status ke `VERIFIED`, catat `verified_by`, `verified_at` (ISO 8601 dengan presisi detik), tandai eligible untuk payroll. (Req 4.2, 4.8)
    - Pada `rejectOutput(OutputEntry $entry, string $reason)`:
      - Validasi: Panjang alasan penolakan wajib antara 10 sampai 500 karakter (`10 <= strlen(trim($reason)) <= 500`). (Req 4.6)
      - Simpan `rejection_reason`, `rejected_by = auth()->id()`, increment `rejection_count`, ubah status ke `REJECTED`. (Req 4.4, 4.8)
      - Kirim notifikasi ke operator/penjahit. Jika notifikasi gagal, jangan rollback transisi status melainkan log error di sistem. (Req 4.4, 4.5)
    - Pada `resubmitOutput(OutputEntry $entry, int $newQuantity, ?string $notes = null): OutputEntry`:
      - Penjahit diizinkan resubmit maksimal dalam 7 hari sejak penolakan dan maksimal 3 kali percobaan per output. (Req 4.7)
      - Jika > 3 percobaan atau > 7 hari, tolak dengan exception `MaxResubmissionsExceededException`.
      - Ubah status dari `REJECTED` kembali ke `SUBMITTED`. (Req 4.9)
- [ ] **3.4** Unit & Feature Test
  - **File Target**: `tests/Feature/Production/OutputResubmissionTest.php`
  - **Command Verifikasi**: `php artisan test --filter=OutputResubmissionTest`

---

### MODUL 4: Line Allocation, Station Rotation & Retensi History (Req 11)
*PIC / Agent: Worker B3*  
*Dependensi: None (Bisa paralel dengan Modul 2 & 3)*  
*Cakupan Requirement: Req 11.1 - 11.8*

- [ ] **4.1** Migrasi Kolom Stasiun pada `line_assignments`
  - **File Target**: Migration baru `database/migrations/xxxx_add_station_to_line_assignments_table.php`
  - **Spesifikasi**: Tambahkan kolom `station_number` (string, nullable).
- [ ] **4.2** Service `LineAllocationService`
  - **File Target**: `app/Services/Production/LineAllocationService.php`
  - **Spesifikasi Logika**:
    - Method `assignOperator(ProductionLine $line, User $operator, ?string $station = null, ?DateTimeInterface $startTime = null, ?DateTimeInterface $endTime = null): LineAssignment`:
      - Catat penugasan dengan `start_time` dan opsional `end_time`. (Req 11.1)
      - Validasi tumpang tindih: Izinkan rotasi antar line dan station tanpa konflik penugasan aktif ganda. (Req 11.2, 11.3)
      - Jika berpindah stasiun/line, record output berikutnya otomatis terasosiasi dengan stasiun/line baru. (Req 11.4, 11.5)
    - Method `rotateStation(LineAssignment $assignment, string $newStation): LineAssignment`.
    - Riwayat penugasan (`LineAssignment`) tidak boleh di-hard delete (retensi minimal 2 tahun). (Req 11.6, 11.7)
- [ ] **4.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Production/LineAllocationServiceTest.php`
  - **Command Verifikasi**: `php artisan test --filter=LineAllocationServiceTest`

---

## TRACK C: QUALITY ASSURANCE & REPAIR WORKFLOW (Independen)

### MODUL 5: Repair Domain (Model, Migration, Service) (Req 13)
*PIC / Agent: Worker C1*  
*Dependensi: None (Mandiri - Menggunakan tabel `qc_records` yang sudah ada)*  
*Cakupan Requirement: Req 13.1 - 13.6*

- [ ] **5.1** Buat Enum `App\Enums\RepairStatus`
  - **File Target**: `app/Enums/RepairStatus.php`
  - **Values**: `PENDING`, `IN_REPAIR`, `COMPLETED`, `MAX_ATTEMPTS_EXCEEDED`.
- [ ] **5.2** Migrasi Tabel `repairs`
  - **File Target**: Migration baru `database/migrations/xxxx_create_repairs_table.php`
  - **Kolom**: `id`, `qc_record_id` (foreignId), `assigned_repairer_id` (foreignId nullable), `status` (string), `repair_attempt` (unsignedSmallInteger default 1), `repair_deadline` (timestamp), `completed_at` (timestamp nullable), `notes` (text nullable), `timestamps`. (Req 13.1)
- [ ] **5.3** Model `App\Models\Repair`
  - **File Target**: `app/Models/Repair.php`
  - **Relasi & Helper**: `belongsTo(QcRecord::class)`, `belongsTo(User::class, 'assigned_repairer_id')`, `canAttemptAgain(): bool` (`$this->repair_attempt < 3`).
- [ ] **5.4** Service `RepairManager`
  - **File Target**: `app/Services/Quality/RepairManager.php`
  - **Spesifikasi Logika**:
    - `createFromQc(QcRecord $qc, ?User $repairer = null): Repair` (deadline = `now()->addHours(48)`, initial attempt = 1). (Req 13.1)
    - `markCompleted(Repair $repair): Repair` (set status `COMPLETED`, kembalikan unit ke queue produksi/re-inspeksi). (Req 13.2, 13.3)
    - `recordFailedAttempt(Repair $repair): Repair`:
      - Jika unit hasil repair gagal QC dan `repair_attempt < 3`: increment `repair_attempt` by 1 dan kembalikan ke repair area. (Req 13.4)
      - Jika unit hasil repair gagal QC dan `repair_attempt == 3`: paksa keputusan `SCRAP`, ubah status ke `MAX_ATTEMPTS_EXCEEDED`. (Req 13.5)
- [ ] **5.5** Unit & Feature Test
  - **File Target**: `tests/Feature/Quality/RepairManagerTest.php`
  - **Command Verifikasi**: `php artisan test --filter=RepairManagerTest`

---

### MODUL 6: QC Inspection Transition, Bonus Counter & Waste Hook (Req 6)
*PIC / Agent: Worker C2*  
*Dependensi Interface: Memanggil `RepairManager` jika status REPAIR (atau fallback graceful jika Modul 5 belum di-merge)*  
*Cakupan Requirement: Req 6.1 - 6.10, Property 6*

- [ ] **6.1** Validasi Prasyarat Inspeksi & Defect Category pada `QcService`
  - **File Target**: `app/Services/Quality/QcService.php`
  - **Spesifikasi Logika**:
    - Prasyarat Inspeksi: Hanya record dengan Output Status `VERIFIED` yang dapat diinspeksi di QC Station. (Req 6.1)
    - Jika hasil `PASS`:
      - Ubah status output ke `PASSED`. (Req 6.4)
      - Naikkan (increment) counter bonus pool QC untuk periode aktif sebanyak +1. (Req 6.4, 6.9, Property 6)
    - Jika hasil `REPAIR`:
      - Wajib input defect category (tolak jika kosong). (Req 6.7, 6.8, 6.10)
      - Ubah status output ke `REPAIR`, arahkan ke repair area, dan buat tiket repair via `RepairManager`. (Req 6.5, 13.1)
    - Jika hasil `SCRAP`:
      - Wajib input defect category & waste category. (Req 6.7, 6.8, 6.10, Req 14.1)
      - Ubah status output ke `SCRAP`, arahkan ke scrap area, dan otomatis catat ke `waste_records`. (Req 6.6)
- [ ] **6.2** Unit & Feature Test
  - **File Target**: `tests/Feature/Quality/QcInspectionFlowTest.php`
  - **Command Verifikasi**: `php artisan test --filter=QcInspectionFlowTest`

---

## TRACK D: WAREHOUSE & INVENTORY (Independen)

### MODUL 7: Inventory State Sequence & Waste Reporting (Req 12, 14)
*PIC / Agent: Worker D1*  
*Dependensi: None (Mandiri)*  
*Cakupan Requirement: Req 12.1 - 12.7, Req 14.1 - 14.5*

- [ ] **7.1** Validasi Urutan Siklus Hidup Inventory di `InventoryService`
  - **File Target**: `app/Services/Inventory/InventoryService.php`
  - **Spesifikasi Logika**:
    - Saat material datang: inisialisasi status `RAW` dan buat unique item id. (Req 12.1)
    - Saat dikeluarkan/dipotong untuk produksi: transisi ke `WIP`. (Req 12.2, 12.3)
    - Saat dirakit/selesai: transisi ke `FINISHED`. (Req 12.4)
    - Validasi sequence: Tolak transisi yang tidak berurutan (`RAW -> WIP -> FINISHED`). (Req 12.5)
    - Transisi ke `WIP` dan `FINISHED` wajib menyertakan `order_id` yang valid. (Req 12.7)
    - Rekam jejak audit (`InventoryMovement` / `AuditLog`) dengan `previous_status`, `new_status`, `actor_id`, `timestamp`. (Req 12.6)
- [ ] **7.2** Service `WasteReporter` & Taksonomi Kategori
  - **File Target**: `app/Services/Quality/WasteReporter.php`
  - **Spesifikasi Logika**:
    - Batasi taksonomi kategori waste maksimal 10 kategori terdefinisi. Jika di luar taksonomi, wajibkan input manual beserta deskripsi. (Req 14.1, 14.4)
    - Method `generateReport(DateTimeInterface $start, DateTimeInterface $end): array`:
      - Validasi: Durasi tanggal antara 1 sampai 90 hari. (Req 14.5)
      - Agregasi: Menghitung total kuantitas per kategori waste. (Req 14.3, 14.5)
- [ ] **7.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Inventory/InventorySequenceAndWasteTest.php`
  - **Command Verifikasi**: `php artisan test --filter=InventorySequenceAndWasteTest`

---

### MODUL 7B: Client Isolation & Accessories Tagging (Req 1, 5)
*PIC / Agent: Worker D2*  
*Dependensi: None (Mandiri)*  
*Cakupan Requirement: Req 1.1 - 1.5, Req 5.1 - 5.7, Property 4, Property 8*

- [ ] **7.4** Verifikasi Isolasi Klien & Tagging Aksesoris Semi-CMT
  - **File Target**: `app/Services/Inventory/InventoryService.php`
  - **Spesifikasi Logika**:
    - Skema CMT: Client pemilik semua material. Semi-CMT: Client pemilik material utama, Lukla pemilik aksesoris. Full Package: Lukla pemilik seluruh material. (Req 1.1 - 1.3)
    - Pada skema Semi-CMT: Aksesoris yang disuplai Lukla wajib diberi tag `client_id` dari order tujuan. (Req 1.4, Req 5.4)
    - Cegah perubahan `work_scheme` setelah status order berpindah ke "In Production". (Req 1.5)
    - Validasi Isolasi Material: Bandingkan `client_id` material dengan `client_id` order tujuan saat material di-issue. Jika berbeda, tolak dengan error client isolation violation. (Req 5.2, 5.3, Property 4)
    - Audit Trail: Setiap movement (RECEIVE, ISSUE, TRANSFER) wajib mencatat `movement_type`, `source_id`, `destination_id`, `quantity`, `actor_id`, `timestamp`. (Req 5.5, 5.6, Property 8)
- [ ] **7.5** Unit & Feature Test
  - **File Target**: `tests/Feature/Inventory/CrossClientIssueRejectionTest.php`
  - **Command Verifikasi**: `php artisan test --filter=CrossClientIssueRejectionTest`

---

## TRACK E: PAYROLL & SETTLEMENT (Paralel via Mock Data)

### MODUL 8: QC Bonus Pool Finalizer, Remainder & Recipient Alert (Req 7)
*PIC / Agent: Worker E1*  
*Dependensi Data: `QcRecord` berstatus PASS (Gunakan Kontrak 1 untuk testing tanpa menunggu Track C)*  
*Cakupan Requirement: Req 7.1 - 7.6, Property 2*

- [ ] **8.1** Service `QcBonusDistributionService`
  - **File Target**: `app/Services/Payroll/QcBonusDistributionService.php`
  - **Spesifikasi Logika**:
    - Method `finalizeBonusPool(PayrollPeriod $period): QcBonusPool`:
      - Hitung unit lolos QC berstatus PASS di rentang periode.
      - Rate per unit: Rp 500 flat (atau dari field `qc_bonus_rate_per_unit`). (Req 7.1)
      - Total pool = `passed_units * 500`.
    - Method `distributeBonus(QcBonusPool $pool): array`:
      - Alokasikan kepada tepat 3 penerima yang aktif selama periode: 2 Mandor (`PRODUCTION_LEAD`) + 1 Kepala Operasional (`OWNER_EXECUTIVE`). (Req 7.2, 7.4)
      - Validasi: Jika jumlah penerima bukan 3, picu alert notification ke Finance dan kirim email notifikasi ke Finance. (Req 7.5)
      - Sisa Pembagian (Remainder): Terapkan floor division (`total_pool / 3`). Sisa pembagian (remainder) dibebankan ke penerima pertama/terakhir agar total pas tanpa selisih rupiah. (Req 7.3, Property 2)
      - Laporan distribusi: Sajikan formula kalkulasi, jumlah per individu, total pool, dan penanganan remainder. (Req 7.6)
- [ ] **8.2** Unit & Feature Test
  - **File Target**: `tests/Feature/Payroll/QcBonusDistributionServiceTest.php`
  - **Command Verifikasi**: `php artisan test --filter=QcBonusDistributionServiceTest`

---

### MODUL 9: Weekly Payroll Period Close, Locking & Payables Report (Req 8)
*PIC / Agent: Worker E2*  
*Dependensi Data: `OutputEntry` VERIFIED & `RateCard` (Gunakan Kontrak 2 untuk testing tanpa menunggu Track B)*  
*Cakupan Requirement: Req 8.1 - 8.8, Property 3, Property 9*

- [ ] **9.1** Method `closePeriod` & Penguncian Output di `PayrollService`
  - **File Target**: `app/Services/Payroll/PayrollService.php`
  - **Spesifikasi Logika**:
    - Penutupan Sabtu 00:00 (UTC+7 dengan toleransi ±1 menit): Tutup periode payroll aktif dan kunci seluruh output berstatus `VERIFIED` menjadi `LOCKED` (tidak boleh diubah lagi). (Req 8.1)
    - Sertakan seluruh pekerja yang memiliki output terverifikasi di rentang periode, tanpa memandang tanggal mulai kerja mereka. (Req 8.5)
- [ ] **9.2** Kalkulasi Base Pay, Uang Makan, Bonus QC, dan Payable Generator
  - **File Target**: `app/Services/Payroll/PayrollService.php`
  - **Spesifikasi Formula**:
    - `Base_Pay = SUM(Verified_Quantity * Rate_Komponen)` dengan presisi 2 desimal. Jika komponen null, abaikan dari kalkulasi dan beri tanda di laporan. (Req 8.2, 8.3)
    - `Meal_Allowance = Base_Pay * meal_allowance_percent / 100` (default 10-15%, default 10% jika tidak diinput khusus, per BR 4.2). (Req 8.4, Property 9)
    - Jika pekerja berhak atas bonus QC individu: hitung proporsional terhadap kontribusi unit QC. (Req 8.7)
    - Generate rincian payable amounts untuk:
      - Penjahit: `Base_Pay + Meal_Allowance + QC_Bonus`.
      - Mandor: `QC_Bonus_Share`.
      - Kepala Operasional: `QC_Bonus_Share`. (Req 8.8)
    - Buat struktur laporan payroll lengkap (identitas pekerja, ringkasan output per order & komponen, base pay, meal allowance, QC bonus, total earnings). (Req 8.6, Property 3)
- [ ] **9.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Payroll/PayrollClosingAndCalculationTest.php`
  - **Command Verifikasi**: `php artisan test --filter=PayrollClosingAndCalculationTest`

---

## TRACK F: SYSTEM INTEGRATION & LIFECYCLE (Tahap Akhir)

### MODUL 10A: Order Lifecycle Guard & Sample Conversion (Req 9, 10)
*PIC / Agent: Worker F1*  
*Dependensi: None (Bisa dieksekusi paralel atau integrasi)*  
*Cakupan Requirement: Req 9.1 - 9.6, Req 10.1 - 10.7, Property 7*

- [ ] **10.1** Verifikasi Siklus Sample ke Order
  - **File Target**: `app/Services/Sample/SampleService.php`
  - **Spesifikasi Logika**:
    - Sample lifecycle: `Draft -> Review -> Approved -> Converted`. (Req 9.1 - 9.3)
    - Konversi ke Order: Salin seluruh data komponen sample ke field order dan ubah status sample ke `Converted`. (Req 9.4, Property 7)
    - Catat entri revisi untuk setiap perubahan sample (`actor_id`, `timestamp`, `changed_fields`, `previous_values`). (Req 9.5)
    - Tolak modifikasi apapun pada Sample yang sudah berstatus `Converted`. (Req 9.6)
- [ ] **10.2** State Machine Siklus Hidup Order
  - **File Target**: `app/Models/Order.php`
  - **Spesifikasi Logika**:
    - Lifecycle: `Pending -> Confirmed -> In Production -> QC Phase -> Completed -> Closed`. (Req 10.1 - 10.6)
    - Pemicu:
      - Deal finalized -> `Confirmed`.
      - Mandor assign penjahit pertama -> `In Production`.
      - 100% unit sampai di QC station -> `QC Phase`.
      - 100% unit berstatus QC PASSED -> `Completed`.
      - Delivery confirm & Invoice PAID -> `Closed`.
    - Ketika status `Closed`: kunci total status, cegah perubahan line item, dan cegah modifikasi rate card. (Req 10.7)
- [ ] **10.3** Unit & Feature Test
  - **File Target**: `tests/Feature/Sample/SampleConversionTest.php`
  - **Command Verifikasi**: `php artisan test --filter=SampleConversionTest`

---

### MODUL 10B: Scheduler, Artisan Command, & Full Regression (Semua Req)
*PIC / Agent: Lead / Integrator*  
*Prasyarat: Semua Track A, B, C, D, E, F1 telah selesai dan di-merge*

- [ ] **10.4** Artisan Command `payroll:close-weekly`
  - **File Target**: `app/Console/Commands/PayrollCloseWeeklyCommand.php`
  - **Spesifikasi**: Memanggil `PayrollService::closePeriod()` dan `QcBonusDistributionService::finalizeBonusPool()`.
- [ ] **10.5** Konfigurasi Jadwal di `routes/console.php`
  - **File Target**: `routes/console.php`
  - **Spesifikasi**:
    ```php
    use Illuminate\Support\Facades\Schedule;
    Schedule::command('payroll:close-weekly')->weekly()->saturdays()->at('00:00')->timezone('Asia/Jakarta');
    ```
- [ ] **10.6** Code Styling & Full Regression Testing
  - Jalankan formatter: `vendor/bin/pint --dirty --format agent`
  - Jalankan seluruh test: `php artisan test` (Wajib 100% Passed).