# Requirements Document

## Introduction

Lukla Operations Engine adalah sistem operasional pabrik konveksi untuk mengelola proses manufaktur garment dengan dukungan multi-client, payroll dinamis berbasis interval 30-menit untuk pekerja borongan, inventory multi-tenant dengan isolasi per-client, dan workflow QC dengan sistem bonus pool.

Sistem ini mendukung tiga skema maklon (CMT, Semi-CMT, Full Package) dengan tracking lengkap mulai dari penerimaan material hingga pengiriman produk jadi dan perhitungan payroll mingguan.

## Glossary

- **CMT (Cut-Make-Trim)**: Skema maklon dimana client menyediakan semua material, Lukla hanya mengerjakan proses produksi
- **Semi-CMT**: Skema maklon dimana client menyediakan material utama, Lukla menyediakan aksesoris
- **Full Package**: Skema maklon dimana Lukla menyediakan semua material dan mengerjakan seluruh proses produksi
- **Owner**: Pemilik pabrik yang bertanggung jawab atas deal agreement dan Rate Order
- **Penjahit**: Pekerja borongan yang menginput output setiap 30 menit per station
- **Mandor**: Supervisor lini produksi yang memverifikasi output penjahit
- **Kepala Produksi**: Bertanggung jawab atas Rate Komponen dan alokasi lini
- **QC Inspector**: Inspektor quality control yang melakukan inspeksi di QC Station
- **Rate Order**: Harga per pesanan yang disepakati Owner dengan client
- **Rate Komponen**: Harga per komponen yang ditentukan Kepala Produksi berdasarkan time study
- **QC Bonus Pool**: Kumpulan bonus dari unit yang PASS QC, didistribusikan ke mandor dan kepala operasional
- **Output Interval**: Periode 30 menit untuk input output oleh penjahit
- **Payroll Period**: Periode payroll mingguan yang ditutup setiap Sabtu
- **Client Isolation**: Aturan yang memastikan material tidak dapat dialihkan ke pesanan client lain

## Requirements

### Requirement 1: Multi-Client Manufacturing Scheme Support

**User Story:** Sebagai Owner, saya ingin sistem mendukung tiga skema maklon (CMT, Semi-CMT, Full Package), sehingga operasional pabrik dapat mengakomodasi berbagai jenis kerjasama dengan client.

#### Acceptance Criteria

1. WHEN an Order is created with CMT scheme, THE Order Manager SHALL record scheme_type as "CMT" and set material_owner to Client
2. WHEN an Order is created with Semi-CMT scheme, THE Order Manager SHALL record scheme_type as "Semi-CMT" and set material_owner to Client for main materials and Lukla for accessories
3. WHEN an Order is created with Full Package scheme, THE Order Manager SHALL record scheme_type as "Full Package" and set material_owner to Lukla
4. WHERE scheme_type is Semi-CMT, THE Inventory Manager SHALL tag Lukla-supplied accessories with client_id of the destination order
5. THE System SHALL prevent modification of scheme_type after Order status transitions to "In Production"

### Requirement 2: Rate Card Management

**User Story:** Sebagai Owner dan Kepala Produksi, saya ingin sistem mengelola struktur rate card dengan jelas (Rate Order dan Rate Komponen), sehingga perhitungan payroll akurat dan margin keuntungan terkontrol.

#### Acceptance Criteria

1. WHEN Deal Agreement is finalized, THE Owner SHALL set Rate Order within 24 hours of finalization
2. WHEN Rate Order is set, THE Rate Validator SHALL compute Available_Margin = Rate_Order - (Material_Cost + Overhead + Profit)
3. IF Available_Margin is less than or equal to zero, THE Rate Validator SHALL reject the Rate Order with error message indicating negative margin
4. WHEN Kepala Produksi sets Rate Komponen, THE Rate Calculator SHALL validate that Rate_Komponen ≤ Available_Margin
5. IF Rate_Komponen exceeds Available_Margin, THE Rate Calculator SHALL reject with error showing allowed maximum
6. WHEN all components are configured, THE Rate Calculator SHALL verify Sum(Rate_Komponen) ≤ Available_Margin
7. IF Sum exceeds Available_Margin, THE Rate Calculator SHALL reject and display shortfall amount
8. THE Rate Manager SHALL enforce sequential Rate Komponen approval (component 1, then 2, etc.)
9. WHEN each Rate Komponen is approved, THE Rate Manager SHALL mark it as "ACTIVE"
10. IF Owner rejects Rate Komponen, THE Rate Manager SHALL transition it to "REJECTED"
11. IF Kepala Produksi modifies approved Rate Komponen, THE Rate Validator SHALL require re-approval workflow
12. WHEN all Rate Komponen are approved, THE Rate Manager SHALL mark Rate Order as "APPROVED"
13. THE Rate Manager SHALL create audit entry for each rate change including: actor_id, timestamp, old_value, new_value
14. THE Rate Manager SHALL retain audit history for minimum 7 years

### Requirement 3: 30-Minute Interval Output Recording

**User Story:** sebagai Penjahit, saya ingin menginput output produksi setiap 30 menit per station, sehingga perhitungan borongan saya akurat dan dapat diverifikasi.

#### Acceptance Criteria

1. WHEN a Penjahit submits output, THE Output Recorder SHALL create an Output record containing: timestamp (UTC with millisecond precision), line_id, station_id, component_id, quantity (integer between 1 and 100)
2. WHEN output is submitted, THE Output Validator SHALL calculate current interval as floor(submission_time minutes / 30) × 30 minutes
3. THE Output Validator SHALL verify submission_time falls within [Interval_Start, Interval_End) where Interval_End = Interval_Start + 30 minutes
4. WHEN submission_time is within valid interval, THE Output Recorder SHALL create Output record with status "SUBMITTED"
5. IF submission_time is outside [Interval_Start, Interval_End), THE Output Validator SHALL reject the submission with error indicating: current time, valid interval range, and next interval start time
6. WHEN output is validated, THE Output Recorder SHALL verify Penjahit_id exists and has active assignment to Line_id; IF verification fails, THE Output Recorder SHALL reject with error indicating invalid Penjahit-Line assignment
7. WHEN output is successfully recorded, THE System SHALL mark it as "PENDING_VERIFICATION"

### Requirement 4: Output Verification Workflow

**User Story:** sebagai Mandor dan Kepala Produksi, saya ingin memverifikasi output penjahit (APPROVE atau REJECT), sehingga hanya output terverifikasi yang eligible untuk payroll.

#### Acceptance Criteria

1. WHEN Mandor or Kepala Produksi accesses the verification view, THE Verifier SHALL display up to 100 pending SUBMITTED output records for their assigned production lines
2. WHEN verifier selects APPROVE for an output record, THE Output Manager SHALL transition the Output status to "Verified" and THE System SHALL mark the Output as eligible for payroll
3. IF verifier attempts to select APPROVE without authorization, THE System SHALL reject the action with an error indicating permission denied
4. WHEN verifier selects REJECT for an output record, THE Output Manager SHALL transition the Output status to "Rejected" and THE System SHALL send a notification to the assigned Penjahit
5. IF notification delivery to Penjahit fails, THE Output Manager SHALL still complete the status transition and record the notification failure in the system log
6. IF verifier selects REJECT, THE Verifier SHALL require a rejection reason with a minimum of 10 characters and a maximum of 500 characters before confirming the REJECT action
7. WHERE Output status is REJECTED, THE Penjahit SHALL be allowed to resubmit corrected output within 7 days of rejection, with a maximum of 3 resubmission attempts per Output
8. THE Audit Logger SHALL record verifier_id, ISO 8601 timestamp with second precision, and decision for every verification action
9. IF Penjahit resubmits rejected output, THE System SHALL transition the Output status from "Rejected" back to "Submitted"

### Requirement 5: Multi-Tenant Inventory with Client Isolation

**User Story:** sebagai Warehouse Manager, saya ingin sistem memastikan material tidak dapat dialihkan ke pesanan client lain, sehingga inventory terkontrol dan tidak terjadi pencampuran antar client.

#### Acceptance Criteria

1. WHEN material is received, THE Inventory Manager SHALL create an Inventory record with client_id, order_id, and item_type
2. WHEN material is issued, THE Inventory Validator SHALL compare client_id of material with client_id of destination order
3. IF client_id of material differs from client_id of destination order, THE Inventory Manager SHALL REJECT the issue with error message indicating client isolation violation
4. WHERE scheme_type is Semi-CMT, THE Inventory Manager SHALL tag Lukla-supplied accessories with client_id of the destination order
5. WHEN inventory movement of type RECEIVE, ISSUE, or TRANSFER occurs, THE Inventory Manager SHALL create audit trail entry with movement_type, source_id, destination_id, quantity, timestamp, and actor_id
6. WHEN inventory movement is recorded, THE Audit Trail SHALL include source_id, destination_id, quantity, and timestamp
7. IF client_id matches AND quantity is available, THE Inventory Manager SHALL deduct quantity from source inventory and transition status to "Issued"

### Requirement 6: QC Inspection with Bonus Pool

**User Story:** sebagai QC Inspector, saya ingin melakukan inspeksi QC dengan hasil PASS/REPAIR/SCRAP dan mengelola bonus pool, sehingga kualitas produk terkontrol dan bonus terdistribusi dengan adil.

#### Acceptance Criteria

1. WHEN QC Inspector initiates inspection, THE QC Manager SHALL display pending Output records with QC Status "PENDING" AND Output status "VERIFIED"
2. IF no Output records meet the prerequisite conditions, THE QC Manager SHALL display empty list with message
3. WHEN QC Inspector selects Output for inspection, THE QC Manager SHALL display component details for review
4. WHEN QC result is PASS, THE QC Manager SHALL transition Output status to "PASSED" and THE System SHALL increment QC Bonus Pool counter by 1
5. WHEN QC result is REPAIR, THE QC Manager SHALL transition Output status to "REPAIR" and route it to repair area
6. WHEN QC result is SCRAP, THE QC Manager SHALL transition Output status to "SCRAP" and route to scrap area
7. WHERE QC result is REPAIR or SCRAP, THE QC Inspector SHALL input defect category before submitting result
8. IF QC Inspector attempts to submit REPAIR or SCRAP result without defect category, THE System SHALL prevent submission and display error indicating required field
9. WHEN QC result is PASS, THE QC Bonus Pool Calculator SHALL increment the counter for the current Payroll Period
10. THE QC Manager SHALL prevent Output submission without defect category for REPAIR and SCRAP results

### Requirement 7: QC Bonus Pool Distribution

**User Story:** sebagai Finance, saya ingin sistem mendistribusikan QC Bonus Pool secara otomatis (2 Mandor + 1 Kepala Operasional), sehingga bonus terdistribusi sesuai aturan bisnis.

#### Acceptance Criteria

1. WHEN Saturday 00:00 arrives (UTC+7), THE Bonus Calculator SHALL finalize QC Bonus Pool = Passed_Units × QC_Bonus_Rate (fixed at Rp 500 per unit)
2. THE Bonus Calculator SHALL compute individual bonus per recipient = QC_Bonus_Pool ÷ 3
3. IF QC_Bonus_Pool is not evenly divisible by 3, THE Bonus Calculator SHALL apply floor division and carry remainder to first recipient
4. WHEN distributing QC Bonus, THE Payroll Manager SHALL allocate shares to exactly 2 Mandor and 1 Kepala Operasional who have active assignment during the payroll period
5. WHERE any recipient count differs from 3, THE System SHALL generate alert notification to Finance AND send email notification to Finance email address
6. THE Bonus Distribution Report SHALL show: calculation formula, individual recipient amounts, total pool, and any remainder handling

### Requirement 8: Weekly Payroll Calculation

**User Story:** sebagai Finance, saya ingin sistem menghitung payroll mingguan dengan Base Pay (Verified Output × Rate Komponen), Meal Allowance, dan QC Bonus, sehingga laporan payroll akurat dan komprehensif.

#### Acceptance Criteria

1. WHEN Saturday 00:00 arrives (UTC+7, with tolerance ±1 minute), THE Payroll Manager SHALL close the current payroll period and transition all output with status "VERIFIED" to "LOCKED"
2. IF Payroll Administrator initiates payroll calculation, THE Payroll Calculator SHALL compute Base_Pay = SUM (Verified_Output_Quantity × Rate_Komponen) with 2 decimal precision
3. IF any Verified_Output has null Rate_Komponen, THE Payroll Calculator SHALL exclude it from calculation and flag in report
4. WHEN Meal Allowance percentage is configured, THE Payroll Calculator SHALL compute Meal_Allowance = Meal_Percentage × Base_Pay; IF not configured, THE Payroll Calculator SHALL use default 10-15% (default 10%) per business requirement
5. THE Payroll Manager SHALL include all workers with verified work between period start AND Saturday 00:00 regardless of their start date
6. THE Payroll Report SHALL include: Worker details, output summary with each Order and Component, Base_Pay per worker, Meal_Allowance per worker, QC_Bonus per worker, and total earnings per worker
7. WHEN Worker is eligible for QC Bonus, THE Payroll Calculator SHALL compute QC_Bonus = (Worker_QC_Contributions ÷ Total_QC_Contributions) × QC_Bonus_Pool
8. THE Payroll Manager SHALL generate payable amounts for: each Penjahit (Base_Pay + Meal_Allowance + QC_Bonus), each Mandor (QC_Bonus_Share), and each Kepala Operasional (QC_Bonus_Share)

### Requirement 9: Sample to Order Conversion

**User Story:** sebagai Owner, saya ingin membuat Sample yang kemudian dapat dikonversi menjadi Order dengan riwayat revisi, sehingga transisi dari pengembangan ke produksi tercatat dengan lengkap.

#### Acceptance Criteria

1. WHEN Sample is created, THE Sample Manager SHALL initialize status as "Draft"
2. WHEN Sample is submitted for review, THE Sample Manager SHALL transition status to "Review"
3. WHEN Client approves Sample, THE Sample Manager SHALL transition status to "Approved"
4. WHEN Owner converts Sample to Order, THE Order Manager SHALL copy Sample data into Order fields AND transition Sample status to "Converted"
5. THE Sample Manager SHALL record a revision entry for each Sample change, including actor_id, timestamp, changed_fields, and previous_values
6. IF Owner attempts to modify a Sample with "Converted" status, THE System SHALL reject the modification with an error message indicating the Sample is converted

### Requirement 10: Order State Management

**User Story:** sebagai Kepala Produksi, saya ingin sistem mengelola status pesanan melalui lifecycle yang jelas (Pending → Confirmed → In Production → QC Phase → Completed → Closed), sehingga tracking status pesanan selalu akurat.

#### Acceptance Criteria

1. WHEN Order is created, THE Order Manager SHALL initialize status as "Pending"
2. WHEN Deal Agreement is finalized, THE Order Manager SHALL transition status to "Confirmed"
3. WHEN Mandor assigns first Penjahit to Order, THE Order Manager SHALL transition status to "In Production"
4. WHEN 100% of expected Order units reach QC Station, THE Order Manager SHALL transition status to "QC Phase"
5. WHEN 100% of produced units have QC Status "PASSED", THE Order Manager SHALL transition status to "Completed"
6. WHEN Owner confirms delivery complete AND Finance marks invoice as "PAID", THE Order Manager SHALL transition status to "Closed"
7. WHERE Order status is "Closed", THE System SHALL prevent status changes, line item modifications, and rate changes

### Requirement 11: Production Line and Station Allocation

**User Story:** sebagai Kepala Produksi, saya ingin mengalokasikan penjahit secara dinamis ke line dan station, sehingga utilisasi mesin dan tenaga kerja optimal.

#### Acceptance Criteria

1. WHEN Kepala Produksi assigns Penjahit to Line, THE Line Manager SHALL record assignment with start_time and optional end_time
2. THE Line Manager SHALL allow Penjahit rotation between Line and Station during production
3. IF Penjahit has overlapping Line assignments, THE Line Manager SHALL validate no conflict and record each separately
4. WHEN Penjahit moves to different Station, THE Output Recorder SHALL associate subsequent output records with new Station for same Line
5. WHERE Penjahit moves to different Line, THE Output Recorder SHALL associate subsequent output records with new Line
6. THE Line Manager SHALL maintain allocation history including: penjahit_id, line_id, station_id, start_time, end_time, and actor_id
7. THE Allocation History SHALL be retained for minimum 2 years for reporting purposes
8. WHERE Penjahit is assigned to multiple Lines in same Payroll Period, THE Payroll Calculator SHALL sum output from all assignments

### Requirement 12: Inventory State Tracking

**User Story:** sebagai Warehouse, saya ingin sistem melacak status inventory melalui lifecycle yang jelas (Received → Issued → Cut → Consumed), sehingga stock selalu akurat dan terdokumentasi.

#### Acceptance Criteria

1. WHEN material arrives, THE Inventory Manager SHALL create Inventory record with status "RAW" and generate unique inventory_item_id
2. WHEN material is issued to production, THE Inventory Manager SHALL transition status to "WIP"
3. WHEN material is cut for production, THE Inventory Manager SHALL transition status to "WIP"
4. WHEN material is consumed in assembly, THE Inventory Manager SHALL transition status to "FINISHED"
5. IF status transition is not in sequence (RAW→WIP→FINISHED), THE Inventory Manager SHALL reject transition with error
6. WHERE status changes, THE Audit Trail SHALL record previous_status, new_status, actor_id, inventory_item_id, and timestamp
7. WHEN status transitions require production context, THE Inventory Manager SHALL require order_id for WIP and FINISHED transitions

### Requirement 13: Repair Workflow Management

**User Story:** sebagai QC Inspector, saya ingin sistem mengelola unit REPAIR yang kembali ke produksi untuk QC ulang, sehingga defect terbaiki dan dapat diterima.

#### Acceptance Criteria

1. WHEN QC result is REPAIR, THE Repair Manager SHALL create Repair record with: repair_reason_id, assigned_repairer_id, repair_deadline (48 hours from creation), and repair_attempt = 1
2. WHEN repair is completed, THE Repair Manager SHALL transition repair status to "COMPLETED" AND return unit to production queue
3. WHEN QC Inspector selects repaired unit for re-inspection, THE QC Manager SHALL allow re-inspection workflow
4. IF repaired unit fails QC (REPAIR or SCRAP) AND repair_attempt < 3, THE Repair Manager SHALL increment repair_attempt by 1 AND return unit to repair area
5. IF repaired unit fails QC (REPAIR or SCRAP) AND repair_attempt = 3, THE Repair Manager SHALL force SCRAP decision AND transition repair status to "MAX_ATTEMPTS_EXCEEDED"
6. THE Repair Tracker SHALL increment counter for each QC REPAIR result AND reset counter to 0 when QC result is PASS

### Requirement 14: Waste and Defect Categorization

**User Story:** sebagai QC Inspector, saya ingin mencatat kategori waste dan defect, sehingga reporting kualitas dapat dianalisis untuk improvement.

#### Acceptance Criteria

1. WHEN QC result is SCRAP, THE QC Inspector SHALL select exactly one Waste Category from predefined taxonomy containing at most 10 categories
2. WHEN defect category taxonomy is configured, THE QC Inspector SHALL select exactly one Defect Category for QC results of REPAIR and SCRAP
3. WHEN QC inspection is submitted, THE Defect Analyzer SHALL aggregate defect counts by category for quality reports
4. WHERE selected category is not in predefined taxonomy, THE System SHALL require manual category entry with description before submission
5. WHEN Waste Report is generated, THE Report Generator SHALL display total waste count by category for specified period of 1 to 90 days

## Correctness Properties

### Property 1: Rate Card Consistency

**Formula**: RATE ORDER = RATE KOMPONEN + MATERIAL + OVERHEAD + PROFIT

**Invariant**: `Rate_Order = Sum(Rate_Komponen) + Material_Cost + Overhead + Profit`

**Testable**: 
- WHEN Rate_Order is set and Rate_Komponen values are computed, THEN Sum(Rate_Komponen) + Material_Cost + Overhead + Profit MUST equal Rate_Order
- WHERE any rate changes, the invariant MUST remain true after validation

### Property 2: QC Bonus Pool Distribution

**Invariant**: `Individual_Bonus = QC_Bonus_Pool / 3`

**Testable**:
- WHEN QC Bonus Pool is finalized, THEN Sum(Individual_Bonuses) MUST equal QC_Bonus_Pool
- WHERE distribution is to 3 recipients, each MUST receive exactly Pool ÷ 3

### Property 3: Payroll Calculation Round Trip

**Round Trip Property**: `Payroll_Report = Calculate_Payroll(Verified_Outputs) → Re-calculate_Payroll(Raw_Data)`

**Testable**:
- WHEN payroll is calculated from Verified_Outputs, THEN re-calculation from source data MUST produce identical results
- WHERE source data is unchanged, payroll recalculation MUST yield same totals

### Property 4: Inventory Client Isolation

**Invariant**: `Material.client_id == Destination_Order.client_id`

**Testable**:
- WHERE material is issued, client_id of material MUST equal client_id of destination order
- WHERE inequality is detected, the issue MUST be rejected
- All accepted movements MUST satisfy the invariant

### Property 5: Output Verification State Machine

**Invariant**: `Output.status ∈ {SUBMITTED, VERIFIED, REJECTED}`

**Transitions**:
- SUBMITTED → VERIFIED (Mandor approval)
- SUBMITTED → REJECTED (Mandor rejection)
- REJECTED → SUBMITTED (Penjahit resubmit)

**Testable**:
- All output records MUST have valid status from the allowed set
- Status transitions MUST follow allowed paths only
- Payroll calculation MUST include only VERIFIED output

### Property 6: QC Bonus Pool Increment on Pass

**Metamorphic Property**: `Pass_Count_New = Pass_Count_Old + 1`

**Testable**:
- WHEN QC result is PASS, THEN QC Bonus Pool counter MUST increment by exactly 1
- WHERE QC result is REPAIR or SCRAP, THEN QC Bonus Pool counter MUST NOT change

### Property 7: Sample to Order Conversion Preservation

**Round Trip Property**: `Order_Data_Contains(Sample_Data)`

**Testable**:
- WHEN Sample is converted to Order, THEN Order MUST contain all component data from Sample
- WHERE Sample has revision history, Order MUST reference the approved revision

### Property 8: Inventory Movement Audit Trail Completeness

**Invariant**: `Audit_Trail.Count == Inventory_Movement.Count`

**Testable**:
- WHERE inventory movement occurs, THEN exactly one audit trail entry MUST be created
- WHERE audit entry exists, corresponding movement MUST exist in history
- Client isolation validation MUST be performed for all movements

### Property 9: Meal Allowance Proportionality

**Formula**: `Meal_Allowance = Meal_Percentage × Base_Pay`

**Testable**:
- WHEN Meal_Percentage is 10-15%, THEN calculated Meal_Allowance MUST equal Meal_Percentage × Base_Pay
- WHERE Base_Pay changes, Meal_Allowance MUST recalculate proportionally

### Property 10: 30-Minute Interval Boundary

**Invariant**: `Output.submission_time ∈ [Interval_Start, Interval_End)`

**Testable**:
- WHEN output is submitted, timestamp MUST fall within exactly one 30-minute interval
- WHERE interval boundaries are 00:00, 00:30, 01:00, etc., output MUST be assigned to correct interval
- No output can exist outside defined intervals

## Dependencies

### System Dependencies

1. **Authentication System**: Users must authenticate before accessing any system function; roles determine access permissions
2. **Database**: PostgreSQL or MySQL for relational data storage with support for transactions and constraints
3. **Filament Admin Panel**: Existing Laravel Filament resources for managing Orders, Inventory, QC Records, and Workers
4. **Notification Service**: Email/SMS notifications for rejected output, payroll completion, and QC results

### Data Dependencies

1. **Client Data**: Existing client records from Filament ClientResource
2. **Order Data**: Existing order structure with multi-scheme support
3. **Inventory Data**: Existing inventory items with client_id and order_id tagging
4. **Worker Data**: Existing Worker model for Penjahit, Mandor, and Kepala Produksi

### Integration Dependencies

1. **Sample Management**: Sample creation and approval workflow before Order conversion
2. **Rate Configuration**: Meal percentage and QC bonus rate configuration before payroll run
3. **Line Configuration**: Production line and station setup before output recording

### External Dependencies

1. **Time Sync**: System clock must be synchronized for accurate 30-minute interval recording
2. **Weekend Processing**: Saturday 00:00 trigger for payroll period close

## Open Questions

| # | Question | Impact | Recommendation |
|---|----------|--------|----------------|
| 1 | QC Bonus Rate per unit (flat atau varies)? | Bonus calculation formula | Clarify if bonus rate is fixed per unit or varies by order type |
| 2 | Meal Allowance % basis (gross atau net)? | Payroll accuracy | Specify if meal percentage applies to gross or net base pay |
| 3 | Rate card change approval workflow? | Audit trail requirements | Define if rate changes require Owner approval and how it is recorded |
| 4 | Waste category taxonomy final? | QC reporting completeness | Confirm final list of waste categories for SCRAP recording |
| 5 | Defect category taxonomy final? | QC tracking and analysis | Confirm final defect categories for REPAIR/SCRAP input |
| 6 | Maximum repair cycles per unit? | Quality control | Define upper limit for repair attempts before forced SCRAP |
| 7 | QC Bonus Pool distribution recipients? | Bonus calculation | Confirm if recipient selection is automatic or configurable |