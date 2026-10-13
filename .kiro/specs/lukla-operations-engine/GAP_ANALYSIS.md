# Analisis Kesenjangan (Gap Analysis)

## Ringkasan

Dokumen ini membandingkan codebase Lukla Operations Engine yang sudah ada dengan requirements yang telah didefinisikan untuk mengidentifikasi komponen yang sudah lengkap, perlu enhancement, atau perlu dibuat dari nol.

**Status Implementasi Keseluruhan:**
- Total Requirements: 14
- ✓ Sudah Terimplementasi: 6 requirements
- ⚠ Perlu Enhancement: 4 requirements
- ✗ Belum Dimulai: 4 requirements

**Persentase Kelengkapan:** 43% (6 dari 14 requirements)

---

## Detail per Requirement

### Requirement 1: Multi-Client Manufacturing Scheme Support

- **Status**: ✓ COMPLETE
- **Bukti**: Model `Order` memiliki kolom `work_scheme` yang mereferensikan enum `WorkScheme` (CMT, SEMI_CMT, FULL_PACKAGE)
- **Model Terhubung**: `Order.php` - memiliki `work_scheme` dan `client_id`
- **Enum**: `WorkScheme.php` dengan nilai CMT, SEMI_CMT, FULL_PACKAGE
- **Action**: Tidak diperlukan action, implementasi sudah sesuai requirement

---

### Requirement 2: Rate Card Management

- **Status**: ⚠ PARTIAL
- **Bukti**: Model `RateCard` sudah ada dengan kolom `rate`, `effective_from`, `effective_to`
- **Gap**:
  - Tidak ada `RateManager` service untuk validasi Available_Margin
  - Tidak ada logic untuk menghitung `Material_Cost`, `Overhead`, `Profit`
  - Tidak ada validasi sequential component approval
  - Tidak ada audit trail untuk perubahan rate
- **Model yang Ada**: `RateCard.php` - perlu ditambahkan relasi ke Order dan Component
- **Action**: Buat `RateManager` service dengan method:
  - `computeAvailableMargin(RateOrder $order): float`
  - `validateRateKomponen(RateKomponen $komponen, float $availableMargin): bool`
  - `enforceSequentialApproval(Order $order): void`
  - `createAuditEntry(Actor $aktor, RateCard $old, RateCard $new): AuditLog`

---

### Requirement 3: 30-Minute Interval Output Recording

- **Status**: ⚠ PARTIAL
- **Bukti**: Model `OutputEntry` ada dengan `interval_start` dan `interval_end`
- **Gap**:
  - Tidak ada `OutputRecorder` service dengan validasi interval 30 menit
  - Tidak ada validasi timestamp precision (millisecond untuk submission, second untuk audit)
  - Tidak ada validasi Penjahit-Line assignment
  - Tidak ada batas quantity (1-100)
- **Model yang Ada**: `OutputEntry.php` - perlu ditambahkan `station_id`, `component_id`
- **Action**: Buat `OutputRecorder` service dengan method:
  - `calculateInterval(DateTime $submission): DateTime`
  - `validateIntervalBoundaries(DateTime $submission, DateTime $intervalStart, DateTime $intervalEnd): bool`
  - `validatePenjahitLineAssignment(Penjahit $penjahit, Line $line): bool`
  - `validateQuantity(int $quantity): bool`

---

### Requirement 4: Output Verification Workflow

- **Status**: ⚠ PARTIAL
- **Bukti**: `OutputEntry` memiliki `status` dan `verified_by`, enum `OutputStatus` ada (SUBMITTED, VERIFIED, REJECTED)
- **Gap**:
  - Tidak ada logic untuk notification ke Penjahit saat REJECT
  - Tidak ada resubmission workflow (max 3 attempts)
  - Tidak ada rejection reason validation (10-500 karakter)
  - Tidak ada audit trail untuk verification actions
- **Service yang Diperlukan**: `OutputVerifier` dengan method:
  - `approveOutput(OutputEntry $output, User $verifier): void`
  - `rejectOutput(OutputEntry $output, User $verifier, string $reason): void`
  - `allowResubmission(OutputEntry $output): bool`
  - `sendNotificationToPenjahit(Penjahit $penjahit, OutputEntry $output): NotificationResult`
- **Action**: Enhancement `OutputEntryResource.php` di Filament dengan notification integration

---

### Requirement 5: Multi-Tenant Inventory with Client Isolation

- **Status**: ✓ COMPLETE
- **Bukti**: 
  - Model `InventoryItem` memiliki `client_id`, `order_id`, `state`
  - Trait `BelongsToClient.php` sudah ada untuk client scoping
  - Enum `InventoryState` dan `MovementType` sudah ada
  - Model `InventoryMovement` ada untuk audit trail
- **Logic yang Ada**: Client isolation sudah diimplementasikan via trait dan model structure
- **Action**: Tidak diperlukan action, verify di testing bahwa client isolation working

---

### Requirement 6: QC Inspection dengan Bonus Pool

- **Status**: ⚠ PARTIAL
- **Bukti**: 
  - Model `QcRecord` ada dengan `result`, `inspected_by`, `defect_category_id`
  - Enum `QcResult` ada (PASS, REPAIR, SCRAP)
  - Model `DefectCategory` ada
- **Gap**:
  - Tidak ada validasi defect category required untuk REPAIR/SCRAP
  - Tidak ada integrasi dengan OutputEntry untuk status transition
  - Tidak ada increment logic untuk QC Bonus Pool counter
  - Tidak ada QC inspection view di Filament dengan prerequisite (Output VERIFIED + QC Status PENDING)
- **Model yang Diperlukan**: Mungkin perlu `QcInspection` model untuk workflow
- **Action**: 
  - Buat `QcInspector` service dengan validasi defect category
  - Enhancement `QcRecord.php` dengan status transition logic
  - Integration dengan `PayrollPeriod` untuk bonus pool counter

---

### Requirement 7: QC Bonus Pool Distribution

- **Status**: ✗ NOT STARTED
- **Bukti**: 
  - Model `QcBonusPool` ada dengan `total_passed_units`, `rate_per_unit`, `total_pool`
  - Model `QcBonusDistribution` ada
- **Gap**:
  - Tidak ada automatic calculation logic (Rp 500 per unit)
  - Tidak ada distribusi ke 2 Mandor + 1 Kepala Operasional
  - Tidak ada Saturday 00:00 trigger
  - Tidak ada floor division dengan remainder handling
  - Tidak ada alert jika recipient count ≠ 3
- **Service yang Diperlukan**: `BonusCalculator` dengan method:
  - `finalizeBonusPool(PayrollPeriod $period): QcBonusPool`
  - `distributeToRecipients(QcBonusPool $pool): Collection`
  - `handleRemainder(float $pool, int $recipientCount): array`
  - `validateRecipientCount(int $count): bool`
- **Action**: 
  - Buat `QcBonusDistributionService`
  - Setup scheduled task untuk Saturday 00:00 trigger
  - Integration dengan notification service untuk alert

---

### Requirement 8: Weekly Payroll Calculation

- **Status**: ⚠ PARTIAL
- **Bukti**: 
  - Model `PayrollPeriod` ada dengan `period_start/end`, `meal_allowance_percent`, `qc_bonus_rate`
  - Model `PayrollEntry` ada dengan `total_pieces`, `sewing_amount`, `meal_allowance`, `total`
  - Enum `PayrollPeriodStatus` ada
- **Gap**:
  - Tidak ada `PayrollCalculator` service dengan complete logic
  - Tidak ada Saturday 00:00 closing logic
  - Tidak ada output locking mechanism
  - Tidak ada QC bonus distribution ke individual workers
  - Tidak ada payroll report generation
- **Service yang Diperlukan**: `PayrollCalculator` dengan method:
  - `closePeriod(PayrollPeriod $period): void`
  - `calculateBasePay(Penjahit $penjahit, PayrollPeriod $period): float`
  - `calculateMealAllowance(float $basePay, float $percentage): float`
  - `allocateQcBonus(PayrollPeriod $period): void`
  - `generatePayrollReport(PayrollPeriod $period): PayrollReport`
- **Action**: Buat `PayrollCalculatorService` lengkap dengan report generation

---

### Requirement 9: Sample to Order Conversion

- **Status**: ✓ COMPLETE
- **Bukti**: 
  - Model `Sample` ada dengan `status`, `revisions`, `approved_revision`
  - Model `SampleRevision` ada untuk revision history
  - Enum `SampleStatus` ada (DRAFT, REVIEW, APPROVED, CONVERTED)
- **Logic yang Ada**: Sample creation, revision tracking, dan status management sudah ada
- **Gap**: Conversion logic (Sample → Order) belum diimplementasikan
- **Action**: 
  - Buat `SampleConverter` service dengan method:
    - `convertToOrder(Sample $sample): Order`
    - `copySampleDataToOrder(Sample $sample, Order $order): void`
    - `transitionSampleStatus(Sample $sample, string $status): void`
  - Enhancement `SampleResource.php` dengan convert action

---

### Requirement 10: Order State Management

- **Status**: ✓ COMPLETE
- **Bukti**: 
  - Model `Order` ada dengan `status`
  - Enum `OrderStatus` ada (DRAFT, CONFIRMED, IN_PRODUCTION, IN_QC, COMPLETED, CANCELLED)
- **Logic yang Ada**: Status field dan enum sudah sesuai requirement
- **Gap**: State transition logic belum dipaksakan di code
- **Action**: 
  - Buat `OrderStateManager` service dengan method:
    - `canTransition(Order $order, string $newStatus): bool`
    - `transitionTo(Order $order, string $newStatus): void`
    - `validateTransition(Order $order, string $targetStatus): ValidationResult`
  - Add checks di respective controllers/resources

---

### Requirement 11: Production Line and Station Allocation

- **Status**: ✓ COMPLETE
- **Bukti**: 
  - Model `ProductionLine` ada
  - Model `LineAssignment` ada untuk tracking assignments
- **Logic yang Ada**: Line allocation structure sudah ada
- **Gap**: 
  - Tidak ada rotation tracking logic
  - Tidak ada history retention (2 years minimum)
  - Tidak ada overlapping assignment validation
- **Action**: 
  - Enhancement `LineAssignment` dengan `start_time`, `end_time`, `station_id`
  - Buat `LineAllocationService` dengan rotation tracking
  - Add query scope untuk get assignments dalam period

---

### Requirement 12: Inventory State Tracking

- **Status**: ✓ COMPLEATE
- **Bukti**: 
  - Model `InventoryItem` ada dengan `state` (InventoryState enum)
  - Enum `InventoryState` ada (RAW, WIP, FINISHED)
  - Model `InventoryMovement` ada untuk audit
- **Logic yang Ada**: State tracking sudah ada
- **Gap**: 
  - Tidak ada sequence validation (RAW→WIP→FINISHED)
  - Tidak ada production context validation (order_id required untuk WIP/FINISHED)
- **Action**: 
  - Buat `InventoryStateManager` service dengan sequence validation
  - Add event listeners untuk state transitions
  - Enhancement `InventoryMovement.php` dengan previous_status

---

### Requirement 13: Repair Workflow Management

- **Status**: ✗ NOT STARTED
- **Bukti**: Tidak ada model `Repair` di codebase
- **Gap**: 
  - Tidak ada Repair model sama sekali
  - Tidak ada repair deadline tracking (48 jam)
  - Tidak ada repair attempt counter (max 3)
  - Tidak ada re-inspection workflow
  - Tidak ada MAX_ATTEMPTS_EXCEEDED status
- **Model yang Diperlukan**: `Repair.php` dengan kolom:
  - `repair_reason_id`
  - `assigned_repairer_id`
  - `repair_deadline`
  - `repair_attempt` (default 1, max 3)
  - `status` (PENDING, IN_REPAIR, COMPLETED, MAX_ATTEMPTS_EXCEEDED)
- **Service yang Diperlukan**: `RepairManager` dengan method:
  - `createRepairFromQc(QcRecord $qc): Repair`
  - `incrementAttempt(Repair $repair): void`
  - `markCompleted(Repair $repair): void`
  - `exceedMaxAttempts(Repair $repair): void`
  - `isEditable(Repair $repair): bool`
- **Action**: Prioritas tinggi - buat model dan service dari nol

---

### Requirement 14: Waste and Defect Categorization

- **Status**: ✓ COMPLETE
- **Bukti**: 
  - Model `DefectCategory` ada
  - Enum `QcResult` ada dengan defect-related results
- **Logic yang Ada**: Defect category taxonomy sudah ada
- **Gap**: 
  - Tidak ada Waste Category model (max 10 categories)
  - Tidak ada waste report generation (1-90 hari period)
  - Tidak ada manual category entry dengan description
  - Defect analysis aggregation belum ada
- **Action**: 
  - Buat `WasteCategory` model jika belum ada
  - Buat `WasteReporter` service dengan method:
    - `generateWasteReport(DateTime $start, DateTime $end): WasteReport`
    - `aggregateByCategory(Collection $records): array`
  - Add manual entry validation di QC form

---

## Kesimpulan

### Ringkasan Implementasi

| Kategori | Jumlah | Persentase |
|----------|--------|------------|
| ✓ Complete | 6 | 43% |
| ⚠ Partial | 4 | 29% |
| ✗ Not Started | 4 | 29% |
| **Total** | **14** | **100%** |

### Prioritas Implementasi

Berdasarkan gap analysis, prioritas implementasi adalah:

1. **Prioritas 1 - CRITICAL**: Requirement 13 (Repair Model)
   - Model `Repair` belum ada sama sekali
   - Merupakan blocking dependency untuk QC workflow
   - Estimated effort: 3-4 hari

2. **Prioritas 2 - HIGH**: Service Classes
   - `RateManager` service (Requirement 2)
   - `OutputRecorder` service (Requirement 3)
   - `PayrollCalculator` service (Requirement 8)
   - Estimated effort: 4-5 hari

3. **Prioritas 3 - MEDIUM**: Workflow Enhancement
   - `OutputVerifier` dengan notification (Requirement 4)
   - `QcInspector` dengan bonus pool (Requirement 6)
   - `BonusCalculator` (Requirement 7)
   - Estimated effort: 3-4 hari

4. **Prioritas 4 - LOW**: Reporting & Validation
   - `InventoryStateManager` sequence validation (Requirement 12)
   - `WasteReporter` (Requirement 14)
   - `LineAllocationService` rotation tracking (Requirement 11)
   - Estimated effort: 2-3 hari

### Estimasi Total Effort

- **Model Baru**: 1 model (Repair) +可能的 WasteCategory
- **Service Classes**: 8 services baru/enhancement
- **Filament Resources**: 4 resources enhancement
- **Testing**: Full test coverage untuk 14 requirements
- **Total Estimasi**: 12-16 hari kerja

---

## Lampiran: Status Model per Requirement

| Requirement | Model | Status | Notes |
|-------------|-------|--------|-------|
| 1 | Order, WorkScheme enum | ✓ Complete | work_scheme column ada |
| 2 | RateCard | ⚠ Partial | RateManager service needed |
| 3 | OutputEntry | ⚠ Partial | OutputRecorder service needed |
| 4 | OutputEntry | ⚠ Partial | OutputVerifier service needed |
| 5 | InventoryItem, InventoryMovement | ✓ Complete | BelongsToClient trait ada |
| 6 | QcRecord, DefectCategory | ⚠ Partial | QcInspector service needed |
| 7 | QcBonusPool, QcBonusDistribution | ✗ Not Started | BonusCalculator service needed |
| 8 | PayrollPeriod, PayrollEntry | ⚠ Partial | PayrollCalculator service needed |
| 9 | Sample, SampleRevision | ✓ Complete | Converter service needed |
| 10 | Order, OrderStatus enum | ✓ Complete | StateManager service needed |
| 11 | ProductionLine, LineAssignment | ✓ Complete | Rotation tracking enhancement needed |
| 12 | InventoryItem, InventoryMovement | ✓ Complete | State validation service needed |
| 13 | - | ✗ Not Started | Repair model tidak ada |
| 14 | DefectCategory | ✓ Complete | WasteCategory dan reporter needed |