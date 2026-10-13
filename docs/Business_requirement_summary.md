# Lukla Operations Engine - Business Requirements Summary

**Tanggal**: Oktober 2026
**Generated dari**: Business Process Analysis Discussions

---

## 1. Executive Summary

Lukla Operations Engine adalah sistem operasional pabrik konveksi untuk mengelola:

- **Multi-client manufacturing** (Snøhund brand internal + klien maklon B2B)
- **Dynamic payroll** dengan perhitungan borongan 30-menit
- **Multi-tenant inventory** dengan client isolation
- **QC workflow** dengan tracking dan bonus pool

---

## 2. Business Context

### 2.1 Cooperation Schemes (Skema Maklon)

| Scheme | Material Owner | Cutting | Sewing | Finishing |
|--------|---------------|---------|--------|-----------|
| **CMT** | Client | Lukla | Lukla | Lukla |
| **Semi-CMT** | Client (main) + Lukla (accessories) | Lukla | Lukla | Lukla |
| **Full Package** | Lukla | Lukla | Lukla | Lukla |

### 2.2 Production Capacity

| Metric | Value |
|--------|-------|
| Active Sewing Machines | ±70 machines |
| Output Interval | 30 minutes |
| Payroll Period | Weekly (Saturday) |
| Line Configuration | Multi-line, Multi-station |

---

## 3. Business Process Flow

### 3.1 Complete Flow Overview

1. DEAL AGREEMENT (Owner ↔ Klien)
   ├── Negosiasi harga dan terms
   └── Rate per ORDER ditentukan Owner

2. SAMPLE CREATION
   ├── Buat sample berdasarkan request
   └── Time study per komponen

3. SAMPLE APPROVAL (Klien)
   └── Klien review dan approve (atau reject → revision)

4. SCHEME DETERMINATION
   └── Tentukan skema: CMT / Semi-CMT / Full Package

5. RATE CARD DETERMINATION
   ├── Rate per ORDER: Owner (di awal deal)
   └── Rate per KOMPONEN: Kepala Produksi (berdasarkan time study)

6. WAREHOUSE
   ├── Satu gudang untuk Bahan dan Aksesoris
   ├── Setiap item memiliki: Tag ID, client_id, order_id, item_type
   ├── Untuk Semi-CMT: Accessories dari Lukla tetap di-tag client_id klien
   └── FR-007: Validasi - reject jika client_id ≠ order_destination

7. PRODUCTION
   ├── Multi-line, Multi-station (conveyor/pass-along)
   ├── Dynamic allocation: Penjahit bisa rotate antar line/station
   ├── Input 30-menit oleh Penjahit (per station)
   ├── Verifikasi oleh Kepala Produksi / Mandor
   └── Component tracking untuk assembly

8. QC PROCESS
   ├── QC Station di akhir line
   ├── QC Inspector melakukan inspeksi
   ├── Hasil: PASS / REPAIR / SCRAP
   ├── Jika REPAIR: Ke repair area, kembali produksi, QC ulang
   ├── Jika SCRAP: Ke scrap area
   └── Setiap PASS: +1 unit ke QC Bonus Pool

9. COMPLETION & DELIVERY
   ├── Packing oleh bagian Produksi
   ├── Packing check (jika tidak sesuai standar → hold/reject)
   ├── Delivery oleh Gudang (surat jalan)
   └── Invoice oleh Finance (berdasarkan rate order)

10. PAYROLL (Setiap Sabtu)
    ├── Close period (lock verified output)
    ├── Base Pay = Σ (Verified Output × Rate Komponen)
    ├── Meal Allowance = 10-15% × Base Pay
    ├── QC Bonus Pool = Σ (Passed Units × QC Bonus Rate)
    ├── QC Bonus Distribution: Pool ÷ 3 (2 Mandor + 1 Kepala Operasional)
    ├── Payroll Report
    └── Owner dapat VIEW reports dan invoices

## 4. Business Rules

### 4.1 Rate Card Relationship

> **Formula**: `RATE ORDER = RATE KOMPONEN + MATERIAL + OVERHEAD + PROFIT`

**Constraint**: Rate Komponen ≤ (Rate Order - Material - Overhead - Profit)
- Kepala Produksi tahu Rate Order saat menentukan Rate Komponen

### 4.2 Meal Allowance Calculation

> **Formula**: `Meal Allowance = Meal% × Base Pay`

| Parameter | Value |
|-----------|-------|
| Default | 10-15% |
| Input oleh | Finance |
| Tenggat | Di awal periode produksi |

### 4.3 QC Bonus Pool

> **Formula**: `QC Bonus Pool = Σ (Passed Units × QC Bonus Rate)`

**Distribution**:
- 2 Mandor
- 1 Kepala Operasional
- Per recipient = Pool ÷ 3

### 4.4 Inventory Isolation Rules (FR-005, FR-006, FR-007)

- Setiap item inventory WAJIB memiliki **client_id + order_id**
- Setiap movement (receive, issue, transfer) WAJIB membawa kedua ID
- Sistem **REJECT** jika client_id material ≠ client_id order destination
- Berlaku untuk **SEMUA item**: Bahan dan Aksesoris
- Untuk Semi-CMT: Accessories dari Lukla tetap di-tag client_id klien

### 4.5 Production Workflow Rules

- **Input 30-menit**: Per penjahit per station
- **Verifikasi**: Kepala Produksi / Mandor (APPROVE / REJECT)
- Setelah **VERIFIED**: Eligible untuk payroll
- Jika **REJECT**: Penjahit bisa resubmit

### 4.6 QC Rules

**3 Hasil Inspeksi**:

| Result | Action | QC Bonus |
|--------|--------|----------|
| **PASS** | Ke Finished Goods | +1 unit ke QC Bonus Pool |
| **REPAIR** | Ke repair area, kembali produksi, QC ulang | ❌ |
| **SCRAP** | Ke scrap area, record waste category | ❌ |

> **Wajib input defect category** jika REPAIR / SCRAP

---

## 5. Role-Based Access Control (RBAC)

| Role | Key Responsibilities |
|------|---------------------|
| **Owner** | Deal agreement, Rate Order, View all reports/invoices |
| **Finance / Payroll** | Invoice creation, Payroll calculation, Reporting, Monitoring |
| **Kepala Produksi** | Rate Komponen, Line allocation, Output verification |
| **Mandor** | Output verification, Receive QC Bonus |
| **Penjahit** | 30-min output input |
| **QC Inspector** | QC inspection, Defect categorization |
| **Warehouse** | Material receive/issue, Delivery |
| **Produksi** | Packing, Packing QC check |

---

## 6. Key Technical Requirements

### 6.1 State Machines

| Entity | States |
|--------|--------|
| **Sample** | Draft → Review → Approved → Converted → Locked |
| **Order** | Pending → Confirmed → In Production → QC Phase → Completed → Closed |
| **Inventory** | Received → Issued → Cut → Consumed |
| **Output** | Submitted → Verified → Assembled → Payroll Ready → Paid → Locked |
| **QC** | Pending → In Inspection → Pass / Repair / Scrap |

### 6.2 Trigger Points

| Event | Trigger | Action |
|-------|---------|--------|
| Sample Approved | `convert_to_order()` | Create Order from Sample |
| Material Issue | `issue_material()` | Validate client_id + order_id match |
| Output Submission | `submit_output()` | Create SUBMITTED record |
| QC Pass | `qc_pass()` | Add to QC Bonus Pool |
| Saturday 00:00 | `payroll_period_close()` | Lock period |
| Payroll Run | `calculate_payroll()` | Generate payroll report |

### 6.3 Tracking Requirements

| Tracking Type | Description |
|---------------|-------------|
| **Component Tracking** | Each product component tracked separately through stations |
| **Assembly Tracking** | Components consumed, finished goods created |
| **Line Allocation** | Penjahit → Line → Station → Time interval |
| **Inventory Movement** | Full audit trail with client_id + order_id |

---

## 7. Acceptance Test Scenarios

| # | Scenario | Expected Result |
|---|----------|-----------------|
| 1 | Cross-client material issue | System REJECT dengan explicit warning |
| 2 | Dynamic headcount payroll | Penjahit baru di tengah minggu terhitung akurat |
| 3 | QC Bonus 3-way distribution | Pool divided equally ke 2 Mandor + 1 Kepala |
| 4 | Sample to Semi-CMT order | Order creation dengan riwayat sample revision |

---

## 8. Open Questions (Pending Business Decision)

| # | Question | Impact |
|---|----------|--------|
| 1 | QC Bonus Rate per unit (flat atau varies)? | Bonus calculation |
| 2 | Meal Allowance % basis (gross atau net)? | Payroll accuracy |
| 3 | Rate card change approval workflow? | Audit trail |
| 4 | Waste category taxonomy final? | Reporting |
| 5 | Defect category taxonomy final? | QC tracking |

---

## 9. Document References

| Source | Description |
|--------|-------------|
| SRS Walkthrough | Original 10-page SRS slides |
| BRD | Full Business Requirements Document |

---

