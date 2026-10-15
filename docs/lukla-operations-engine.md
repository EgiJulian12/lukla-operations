# Lukla Operations Engine — Business Requirements Document

## 1. Executive Summary & Problem Framing

### 1.1 Visi Produk (Product Vision)

Membangun platform operasi pabrik untuk Lukla Manufacturing, Snøhund, dan klien maklon B2B yang menjaga kepemilikan material, menelusuri produksi dari sample sampai barang jadi, dan menghitung biaya tenaga kerja secara akurat pada kapasitas produksi yang berubah.

### 1.2 Problem Statement (Masalah Utama)

- **Risiko kontaminasi silang stok:** Material mentah, WIP, dan barang jadi milik banyak klien diproses secara bersamaan tanpa pemisahan sistemik. Material Klien A dapat tertukar dengan pesanan Klien B, sehingga menimbulkan risiko biaya, keterlambatan, dan sengketa kepemilikan.
- **Payroll dan headcount dinamis:** Jumlah penjahit berubah mengikuti volume order. Perhitungan borongan mingguan berbasis output 30 menit, uang makan, bonus QC, dan overhead membutuhkan pencatatan yang konsisten dan dapat diaudit.
- **Histori sample dan revisi tidak terpusat:** Perubahan spesifikasi sample, time study, pemakaian bahan, defect, dan konversi sample ke order massal perlu ditelusuri dalam satu riwayat yang dapat diverifikasi.

### 1.3 Target Audience / User Persona

- **Primary persona: Tim operasi pabrik Lukla:** Pemilik proses sample, gudang, produksi, QC, dan payroll yang membutuhkan data operasional akurat untuk keputusan harian dan perhitungan mingguan.
- **Secondary persona: Snøhund Brand Management dan klien maklon B2B:** Pemilik order yang membutuhkan visibilitas progres, status QC, dan stok milik sendiri tanpa melihat data klien lain.

## Executive summary

Lukla Manufacturing needs a shared operational system for its Ciwidey garment facility, which serves the internal Snøhund brand and multiple B2B maklon clients at the same time. The current operating model makes client-owned materials vulnerable to cross-contamination, requires complex manual payroll reconciliation, and loses the history connecting sample revisions to mass-production orders. The Lukla Operations Engine will establish client- and order-level traceability, dynamic production tracking, quality-control workflows, and flexible payroll and cost reporting so the facility can scale its active workforce and machines without losing operational control.

## 2. Business Goals & Success Metrics (OKRs / KPIs)

Success will be measured by operational outcomes, not only by feature delivery. Baselines and final targets must be confirmed by the business before the go-live review.

| Kategori | Indikator Metrik (KPI / OKR) | Nilai Saat Ini (Baseline) | Target Pasca Rilis |
|----------|------------------------------|---------------------------|--------------------|
| Traceability | Persentase inventory records dengan client ID dan order ID yang valid | TBD | 100% |
| Inventory control | Transaksi issue lintas klien yang berhasil tercatat | TBD | 0 |
| Efficiency | Waktu rekonsiliasi payroll borongan mingguan | TBD | TBD |
| Payroll accuracy | Variansi kalkulasi terhadap periode sampel yang disetujui | TBD | 0 variansi tidak terjelaskan |
| Production visibility | Interval output 30 menit yang tercatat dan diverifikasi | TBD | TBD |
| Quality | Record pass, repair, dan scrap yang terhubung ke client dan order | TBD | 100% |
| Client visibility | Insiden akses lintas klien tanpa otorisasi | TBD | 0 |

## Business objective

- Protect client ownership and reduce the risk of material, work-in-progress, and finished-goods mix-ups across CMT, Semi-CMT, and Full Package work.
- Improve the accuracy and auditability of weekly piece-rate payroll, meal allowances, QC bonuses, and monthly overhead allocation while supporting a dynamically changing sewing workforce.
- Preserve a reliable revision history from sample and time-study work through approved mass-production orders.
- Give operations, finance, quality, executives, and maklon clients timely visibility into the status of work relevant to them.

## Scope

### In scope

- A single operational workflow covering sample and time study, mass-order approval, multi-client receiving and cutting, dynamic production lines, QC and finished goods, and payroll/cost reporting.
- Client and order identification for raw materials, work in progress, finished goods, and every inventory movement.
- Automatic prevention and explicit warning for inventory issues where the client and order do not match.
- Sample revision versioning, component-level time study, and traceability when an approved sample becomes a mass-production order.
- Dynamic allocation of sewing operators across parallel production lines, including approximately 70 active machines and growth beyond a fixed slot count.
- Thirty-minute production-output capture, supervisor verification, component assembly, and transition to QC.
- QC outcomes of pass, repair, and scrap/defect, including defect categories and a shared QC bonus pool.
- Weekly piece-rate payroll, configurable meal allowance percentage, three-way QC bonus distribution, and monthly overhead-per-unit reporting.
- Role-based access for the eight operational personas defined in the SRS and client-scoped access for maklon customers.
- Operational dashboards and touch-friendly production and QC views.

## Actor scope and permissions

The following actor boundaries define which business data each role may view or change. Permissions are scoped to the actor's operational responsibility, client, order, and record state. A permission does not override a locked transaction, a verification state, or a cross-client ownership rule.

| Actor | Data scope | Allowed actions | Explicit restrictions |
|-------|------------|-----------------|-----------------------|
| Owner / Executive Internal | All clients, orders, production, QC, payroll, and configuration summaries | View cross-client analytics, configure approved system settings, review audit history, and approve policy changes | Cannot alter locked transaction history or rewrite audit records |
| Tim Sample / Sample Team | Assigned samples, components, time studies, and sample revisions | Create sample records, record material usage, run component time studies, create revisions, and submit versions for approval | Cannot approve a mass-production order without the required confirmation |
| Gudang / Warehouse Stock | Inventory and movements for authorized receiving and issue operations | Receive client materials, tag client and order ownership, issue materials to cutting, record stock opname, and record waste or scrap | Cannot issue material when client ID or order ID does not match the destination |
| Kepala Produksi / Mandor Line | Assigned production lines, operators, component output, and operational progress | Allocate operators, assign lines, verify 30-minute output, monitor production, and review exceptions | Cannot change payroll formulas, rate-card policy, or final QC results |
| Penjahit / Tailor Operator | Assigned tasks, line, component, and own unverified output | View assigned work and submit completed quantities for each 30-minute interval | Cannot edit output after supervisor verification or view unrelated client payroll data |
| QC Inspector / Quality | Products and components assigned for inspection | Inspect finished goods, record pass, repair, scrap, and defect categories, and route repair work | Cannot change client order data, rate cards, or payroll values |
| Finance & Payroll | Approved output, rate cards, payroll periods, QC bonus inputs, meal allowance inputs, and overhead records | Create or approve material purchase orders, maintain approved financial inputs, calculate payroll, issue invoices, and review cost reports | Cannot change physical warehouse stock or bypass output verification |
| Klien Maklon / B2B Portal | Records where `client_id` matches the authenticated client | View own order status, progress, approved QC status, and own consigned-material balance | Cannot view or modify another client's records, internal payroll, rate cards, or factory-wide data |

**Permission principles:** least privilege, client and order isolation, separation of duties for approval actions, immutable locked records, and auditability for every privileged change.

### Out of scope

- Replacing physical warehouse, cutting, sewing, or inspection equipment.
- Defining employment contracts, tax rules, statutory payroll deductions, or compensation policies not stated in the source SRS.
- External accounting, banking, payment-gateway, logistics, or HR integrations not specified in the source SRS.
- Automated purchasing or supplier-management workflows beyond the stated ability to create or approve material purchase orders.
- A design specification for database, hosting, programming language, or deployment architecture.
- Individual stakeholder names, committed launch dates, and KPI baselines not supplied by the business.

## 3. Agile Scope Boundary (MVP Scoping)

The MVP boundary focuses on the minimum operational loop required to protect inventory ownership, track production, complete QC, and calculate payroll.

### In-Scope (Fokus MVP / Rilis Awal — Status per Oktober 2026)

- [x] Sample and time-study tracking with revision history (`SampleService`, `SampleRevision`, `TimeStudy`).
- [x] Approval of an eligible sample and conversion to a mass-production order (`SampleService::convertToOrder`, `ConvertToOrderAction`).
- [x] CMT, Semi-CMT, and Full Package order classification (`Order`, `WorkScheme`, `WorkSchemeInventoryHandlingTest`).
- [x] Multi-client inventory receiving, client/order tagging, cutting issue validation, and waste tracking (`InventoryService`, `MaterialDispatchService`, `tag_id`, `WasteRecord`).
- [ ] Dynamic production-line allocation and 30-minute output entry with supervisor verification (30-min capture & supervisor verify selesai; rotasi line & station number dalam penyelesaian).
- [x] Component assembly and QC status of pass, repair, or scrap/defect (`Assembly`, `ProductionService::assemble`, `QcService`, `RepairManager`).
- [x] Weekly piece-rate payroll, configurable meal allowance, QC bonus pool, and monthly overhead-per-unit reporting (`PayrollService`, `QcBonusDistributionService`).
- [x] Role-based access for the eight operational personas and client-scoped data visibility (`UserRole`, Spatie Permission, `BelongsToClient`, client portal).
- [x] Operational dashboard, floor production terminal, QC terminal, and payroll workspace (Filament resources & actions).

### Out-of-Scope (Backlog Fase / Iterasi Berikutnya)

- [ ] Statutory payroll deductions, tax filing, attendance, leave, or employment-contract management.
- [ ] External accounting, banking, payment-gateway, logistics, or HR integrations.
- [ ] Supplier procurement automation beyond the material purchase-order capability already identified in the SRS.
- [ ] Replacement or remote control of physical warehouse, cutting, sewing, or inspection equipment.
- [ ] Native mobile applications, multi-language support, and multi-currency support unless separately approved.

## Business requirements

- **BR-001** — The business shall be able to operate internal Snøhund and B2B maklon production in parallel under CMT, Semi-CMT, and Full Package cooperation models. Rationale: Different material ownership obligations must remain explicit for every order.
- **BR-002** — The business shall maintain end-to-end ownership and order traceability for raw materials, WIP, and finished goods, with no unowned inventory records. Rationale: Client-owned stock must not be mixed or misallocated.
- **BR-003** — The business shall be able to plan and monitor production capacity dynamically across approximately 70 active sewing machines and additional capacity when required. Rationale: Headcount and machine utilization change with order volume.
- **BR-004** — The business shall capture verified work output at a 30-minute interval and connect component output to assembled products awaiting QC. Rationale: Piece-rate payroll and operational control depend on timely, attributable output.
- **BR-005** — The business shall retain a versioned history of sample specifications, component time studies, and the approved sample version used for mass production. Rationale: Revisions must be auditable and reproducible.
- **BR-006** — The business shall calculate weekly piece-rate payroll without a fixed headcount limit, using verified output and the applicable client/article/component rate. Rationale: The workforce is variable and payroll must remain accurate as operators join or leave during a period.
- **BR-007** — The business shall calculate configurable meal allowances, a shared QC bonus pool, and monthly overhead per unit using approved operational inputs. Rationale: These costs vary by policy and production period.
- **BR-008** — The business shall enforce role-appropriate access and protect each maklon client's data from other clients. Rationale: Operational confidentiality and separation are required for a multi-client facility.

## Functional requirements

- **FR-001** — The system shall record a sample, its client or internal-brand ownership, component list, and successive revisions such as v1 and v2. Satisfies: BR-005.
- **FR-002** — The system shall record component-level time studies using a digital timer and retain the recorded duration with the applicable sample revision. Satisfies: BR-005.
- **FR-003** — The system shall support sample review and approval before a sample can be used to create a mass-production order. Satisfies: BR-001, BR-005.
- **FR-004** — The system shall create a mass-production order from an approved sample while retaining the approved revision and selected CMT, Semi-CMT, or Full Package model. Satisfies: BR-001, BR-005.
- **FR-005** — On receipt, the system shall require both a client identifier and an order identifier for every raw-material, WIP, and finished-goods inventory record. Satisfies: BR-002.
- **FR-006** — The system shall require the same client and order identifiers on every inventory movement, including receipt, issue to cutting, transfer, adjustment, and finished-goods movement. Satisfies: BR-002.
- **FR-007** — Before recording an issue, the system shall compare the selected material's client and order identifiers with the destination order and reject mismatches with an explicit warning. Satisfies: BR-002.
- **FR-008** — The system shall record waste and scrap quantities with a category and client/order attribution. Satisfies: BR-002, BR-007.
- **FR-009** — The system shall allow authorized production leads to allocate operators across parallel lines without a fixed maximum line-slot count. Satisfies: BR-003.
- **FR-010** — The system shall maintain rate cards for cutting and sewing by article, component, and client, including effective changes and history. Satisfies: BR-003, BR-006.
- **FR-011** — The system shall accept the completed quantity for each operator or component at each 30-minute interval and identify the responsible line and work period. Satisfies: BR-004, BR-006.
- **FR-012** — The system shall allow a supervisor to verify or reject submitted output before it becomes payroll-eligible. Satisfies: BR-004, BR-006.
- **FR-013** — The system shall assemble verified component output into whole-product records and move completed products to a QC-ready state. Satisfies: BR-004.
- **FR-014** — The system shall record QC outcomes as pass, repair, or scrap/defect and capture a defect category when applicable. Satisfies: BR-007.
- **FR-015** — The system shall calculate weekly piece-rate payroll on Saturday from verified output multiplied by the applicable sewing rate, without limiting the number of operators included. Satisfies: BR-006.
- **FR-016** — The system shall allow an authorized user to enter the meal-allowance percentage for a production period and calculate the resulting amount from the defined sewing-cost basis. Satisfies: BR-007.
- **FR-017** — The system shall calculate a QC bonus pool from passed units across clients and orders, then divide the pool equally among two supervisors and one operations head. Satisfies: BR-007.
- **FR-018** — The system shall record monthly electricity, water, internet, and other approved operating costs and calculate overhead per unit by dividing the month's total overhead by total units produced. Satisfies: BR-007.
- **FR-019** — The system shall provide an operational dashboard showing active lines, assigned operators, order progress by client, and 30-minute production speed. Satisfies: BR-003, BR-004, BR-008.
- **FR-020** — The system shall provide touch-friendly production-output and QC views suitable for use on the factory floor. Satisfies: BR-004, BR-007.
- **FR-021** — The system shall enforce the following access boundaries: executives cannot alter locked transaction history; sample staff cannot approve mass orders without confirmation; warehouse staff cannot issue a client/order mismatch; production leads cannot alter payroll formulas or final QC results; operators cannot edit verified output; QC inspectors cannot alter client order pricing; finance cannot alter physical warehouse stock; and clients can access only their own client identifier. Satisfies: BR-008.
- **FR-022** — The system shall retain an audit history for locked transactions, rate-card changes, sample approvals, inventory movements, output verification, QC decisions, and payroll calculations. Satisfies: BR-002, BR-005, BR-006, BR-008.
- **FR-023** — The system shall assign each authenticated user exactly one or more approved actor roles and shall evaluate every view, create, update, approve, issue, verify, QC, and payroll action against the actor's role, client scope, order scope, and record state. Satisfies: BR-008.
- **FR-024** — The system shall prevent a user from elevating their own permissions or approving an action that their role is explicitly prohibited from approving. Satisfies: BR-008.
- **FR-025** — The system shall apply client scope to every maklon-facing query and transaction so that a client actor can access only records whose `client_id` matches the authenticated client. Satisfies: BR-008.
- **FR-026** — The system shall require the designated approval role for sample approval, output verification, final QC status, payroll approval, and policy or rate-card changes, and shall record the approving user and timestamp. Satisfies: BR-005, BR-006, BR-007, BR-008.

## Non-functional requirements

- **NFR-001** — The system shall support operational records for at least 70 active sewing machines and shall not impose a fixed software slot limit that prevents additional active machines or operators from being recorded. Rationale: BR-003.
- **NFR-002** — Inventory issue validation shall complete before the transaction is recorded and shall present the mismatch warning in the same user action. Rationale: BR-002.
- **NFR-003** — Production-dashboard data shall refresh automatically after a valid 30-minute output submission and shall show the updated line/order status without requiring a manual data export. Rationale: BR-003, BR-004.
- **NFR-004** — Access control shall prevent a client from reading or modifying another client's operational records, including inventory, orders, production progress, QC, and payroll-related data. Rationale: BR-008.
- **NFR-005** — Locked transaction history, sample revisions, rate-card history, verification records, QC decisions, and payroll inputs shall be retained in a tamper-evident audit trail. Rationale: BR-002, BR-005, BR-006, BR-008.
- **NFR-006** — The system shall identify the user, timestamp, client, order, and affected record for every auditable create, update, approval, rejection, issue, verification, QC, and payroll action. Rationale: BR-008.
- **NFR-007** — The floor-production and QC interfaces shall remain usable on touch devices with large, clearly distinguishable controls for the required input and review actions. Rationale: BR-004, BR-007.
- **NFR-008** — The business shall define and approve service availability, recovery objectives, retention duration, personal-data handling, and performance targets before go-live; until approved, these values remain open questions. Rationale: BR-008 and operational risk management.
- **NFR-009** — Authorization checks shall be enforced consistently for direct actions and indirect access paths, including dashboards, exports, search, reports, and client-portal views. Rationale: BR-008.
- **NFR-010** — Every denied privileged action shall produce an actionable error for the actor and an audit event containing the actor, attempted action, target record, client scope, timestamp, and denial reason. Rationale: BR-008.

## Stakeholders and sign-offs

| Name | Role | Sign-off required | Status |
|------|------|-------------------|--------|
| TBD | Lukla Manufacturing Operations Team — business owner | yes | pending |
| TBD | Snøhund Brand Management — internal brand owner | yes | pending |
| TBD | Executive sponsor / owner representative | yes | pending |
| TBD | Finance and Payroll representative | yes | pending |
| TBD | Production and Warehouse representative | yes | pending |
| TBD | Quality-control representative | yes | pending |
| TBD | Technology lead / delivery owner | yes | pending |
| TBD | Client representative for maklon portal/data boundaries | yes | pending |

## Dependencies

- **D-01** — Approved ownership and order-identification policy for all incoming materials and transfers — owner: Lukla Manufacturing Operations Team — blocking: yes.
- **D-02** — Approved rate cards for cutting and sewing by client, article, and component — owner: Production and Finance — blocking: yes.
- **D-03** — Approved payroll policy for meal allowance percentage, QC bonus rate, and overhead categories — owner: Finance and Payroll — blocking: yes.
- **D-04** — Named role owners and access approvals for the eight RBAC personas — owner: Executive sponsor — blocking: yes.
- **D-05** — Agreement on client data visibility and the scope of the maklon client portal — owner: Lukla Manufacturing Operations Team and client representatives — blocking: yes.
- **D-06** — Availability of representative historical samples, orders, rate cards, inventory, output, QC, and payroll data for validation — owner: Operations and Finance — blocking: no.

## Risks

- **R-01** — Incomplete client/order tagging at receiving could create untraceable inventory before the system can enforce the rule. Owner: Warehouse lead. Mitigation: approve receiving procedures and reject incomplete intake records before operational use.
- **R-02** — Rate cards or payroll policies may change during implementation and produce disputed calculations. Owner: Finance and Payroll lead. Mitigation: approve effective-dated policy inputs and reconcile calculated results against signed sample periods.
- **R-03** — Inaccurate or late 30-minute output verification could flow into payroll. Owner: Production lead. Mitigation: require supervisor verification, expose exceptions, and perform a pre-payroll review.
- **R-04** — Ambiguous client visibility rules could expose confidential operational data. Owner: Executive sponsor and client-data representative. Mitigation: approve the client-data matrix before access configuration and test cross-client denial scenarios.
- **R-05** — Missing baseline and service-level targets could make launch readiness unmeasurable. Owner: Executive sponsor and technology lead. Mitigation: resolve the open questions in this BRD before the go-live review.

## Timeline / milestones

- **TBD** — BRD stakeholder review and named sign-off — target date to be confirmed.
- **TBD** — Sample-to-mass-order and inventory-isolation acceptance review — target date to be confirmed.
- **TBD** — Production-output, QC, payroll, and overhead calculation acceptance review — target date to be confirmed.
- **TBD** — Operational readiness and go-live decision — committed date to be confirmed.

## 7. Delivery Roadmap & Sprint Milestones

The roadmap is organized around operational validation gates. Sprint numbers and dates remain targets until the named sponsor approves them.

| Milestone | Scope / deliverable | Date | Status |
|-----------|---------------------|------|--------|
| Sprint 0: Setup and prototype | Confirm process maps, role matrix, data definitions, acceptance examples, and floor-device constraints. | TBD | target |
| Sprint 1 to 2: MVP operational foundation | Deliver sample revision, order classification, client/order tagging, inventory controls, and access boundaries. | TBD | target |
| Sprint 3 to 4: Production and payroll loop | Deliver line allocation, 30-minute output, verification, assembly, QC, payroll, QC bonus, and overhead calculations. | TBD | target |
| Internal alpha / beta | Run representative sample-to-order, cross-client inventory, payroll, QC, and client-visibility scenarios with selected operational users. | TBD | target |
| Hardening and defect resolution | Resolve acceptance findings, reconcile calculation results, complete audit/access checks, and confirm readiness evidence. | TBD | target |
| Go-live review | Business sponsor reviews the acceptance evidence and approves or rejects operational release. | TBD | committed |

**Review gate:** Go-live approval requires successful evidence for the four critical scenarios: cross-client issue rejection, dynamic payroll headcount, three-way QC bonus distribution, and sample revision to Semi-CMT mass order.

## Open questions

- Who are the named individuals authorized to sign for operations, Snøhund, executive ownership, finance/payroll, production/warehouse, QC, technology, and maklon clients?
- What are the current baselines and target values for inventory discrepancies, payroll reconciliation time/error rate, sample-to-order traceability, QC turnaround, and client data incidents?
- What availability target, response-time target, recovery point objective, recovery time objective, audit-retention period, and personal-data controls are required for go-live?
- What exact rules define the meal-allowance percentage basis and the QC bonus rate per passed unit?
- Which users may create, approve, revise, lock, or reopen a rate card, payroll period, QC result, inventory adjustment, or sample revision?
- Which client-facing views are required in the first release, and which operational or payroll fields must never be exposed to clients?
- What committed milestone dates or contractual deadlines govern delivery?
- Are statutory payroll deductions, taxes, attendance, leave, or external accounting exports required in a later scope?
