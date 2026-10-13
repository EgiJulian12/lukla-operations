# Lukla Operations Engine - Design Document

## Overview

The Lukla Operations Engine is a comprehensive manufacturing operations management system designed for garment production facilities. It provides multi-client support with three distinct maklon schemes (CMT, Semi-CMT, Full Package), enabling flexible manufacturing partnerships. The system manages production workflows from order creation through quality control to delivery, incorporating real-time output tracking with 30-minute interval recording for piece-rate workers, automated payroll calculations with meal allowances and QC bonus distribution, and multi-tenant inventory management with strict client isolation to prevent material cross-contamination between client orders.

The architecture follows a domain-driven design approach, separating core business domains into distinct bounded contexts: Order Management, Output Recording and Verification, Quality Control, Inventory Management, Payroll Processing, and Rate Card Administration. Each domain maintains its own state machine, business rules, and data integrity constraints while integrating through well-defined interfaces. The system leverages the existing Filament admin panel infrastructure, extending its capabilities with specialized workflows for production floor operations, real-time reporting, and compliance tracking.

Key architectural principles include client isolation as a fundamental constraint enforced at the data layer, event-driven state transitions for all workflow entities, audit trail completeness for regulatory compliance spanning seven years for rate changes and two years for allocation history, and time-bound operations synchronized to UTC+7 with minute-level precision for interval calculations and payroll period boundaries.

## Architecture

### System Context

The Lukla Operations Engine operates within a Laravel application ecosystem, integrating with existing Filament resources for administrative functions while introducing new domain logic for production operations. The system receives input from multiple user roles: Penjahit (piece-rate workers) submitting output at 30-minute intervals, Mandor and Kepala Produksi (supervisors) verifying output and managing line assignments, QC Inspectors performing quality inspections and recording defects, Kepala Produksi configuring rate components and managing production lines, Owners setting rate orders and managing client agreements, and Finance processing weekly payroll and bonus distributions.

Output flows to external systems including the Filament admin panel for management dashboards, notification services for worker alerts, accounting systems for payroll disbursement, and reporting engines for quality and efficiency analytics. The system maintains its own database schema for operational data while referencing existing Filament tables for client, worker, and configuration data.

### Layered Architecture

The architecture employs a layered pattern with clear separation of concerns. The presentation layer encompasses Filament resource pages for administrative functions, custom production floor interfaces for output submission and verification, QC inspection screens with defect categorization, and management dashboards for monitoring and reporting. The application layer coordinates use cases across domains, managing cross-domain transactions for payroll calculation and bonus distribution, orchestrating state machine transitions for order and output workflows, handling multi-step workflows such as sample-to-order conversion and repair tracking, and enforcing authorization checks across all operations.

The domain layer contains the core business logic organized into bounded contexts. Each context encapsulates its own entities, value objects, aggregates, and domain events. The Order Management context manages order lifecycle, rate cards, and scheme-specific rules. The Production context handles output recording, verification workflows, and line assignments. The Quality Control context implements inspection workflows, bonus pool management, and defect tracking. The Inventory context manages material tracking, client isolation, and movement auditing. The Payroll context calculates worker earnings, meal allowances, and bonus distributions.

The infrastructure layer provides technical implementations of domain interfaces. The persistence module implements repositories for all aggregates using Laravel's Eloquent ORM. The messaging module publishes domain events for state changes and workflow transitions. The audit module records all state transitions and business rule validations. The time service provides synchronized timestamps for interval calculations and period boundaries.

### Domain Event Flow

State transitions in all domains generate domain events that trigger downstream processes. When an output record reaches VERIFIED status, it becomes eligible for payroll calculation and QC inspection queue. When QC inspection yields PASS, the QC bonus pool counter increments. When payroll period closes on Saturday at midnight, all verified output locks and payroll calculation begins. These events flow through an event bus that supports synchronous and asynchronous handlers, allowing real-time updates for production monitoring while separating computationally intensive processes like payroll calculation.

### Multi-Tenancy Model

Client isolation operates at the database level through explicit client_id references on all transactional records. The system implements a soft multi-tenancy model where clients share the same database schema but maintain logical separation through foreign key constraints and business rule validation. Every order, inventory item, output record, and payroll entry references a client_id that determines its ownership and isolation boundary. This approach enables efficient reporting across clients while enforcing strict separation for material movements and financial calculations.

### Scheme-Specific Processing

Each maklon scheme requires distinct handling for material ownership, cost structures, and rate calculations. In CMT mode, clients own all materials, Lukla charges purely for production labor, and rate cards include only component rates without material costs. Semi-CMT mode requires dual tracking of client-owned main materials and Lukla-provided accessories, with rate cards combining both cost elements. Full Package mode treats Lukla as the complete supplier, with rate orders including material costs, overhead, and profit margins alongside component rates. The scheme_type determines which validation rules apply and which fields are required in order and rate card records.

## Components and Interfaces

### Order Management Component

The Order Management component coordinates the order lifecycle from creation through completion. The Order Manager handles order creation with scheme validation, ensuring scheme_type cannot change after production begins. It enforces status transition rules based on trigger conditions: Deal Agreement finalization moves status to Confirmed, first Penjahit assignment moves to In Production, 100% of expected units reaching QC moves to QC Phase, 100% of units passing QC moves to Completed, and Owner delivery confirmation with Finance invoice payment moves to Closed. The component integrates with the Rate Card component to retrieve component rates for payroll calculation and with the Inventory component to validate material availability.

The Order Repository provides CRUD operations for Order aggregates, supporting queries by client_id for multi-tenant filtering, by status for workflow dashboards, by scheme_type for reporting, and by date range for historical analysis. The Order Factory encapsulates creation logic for each scheme type, initializing appropriate defaults and invoking rate validation.

### Rate Card Component

The Rate Card component manages the hierarchical rate structure from Deal Agreement through Rate Order to individual Rate Komponen. The Rate Manager handles the approval workflow, enforcing sequential component approval and audit trail creation for all changes. The Rate Calculator validates margin constraints, computing Available_Margin as Rate_Order minus Material_Cost, Overhead, and Profit, then ensuring each Rate Komponen fits within the margin and the sum of all components does not exceed available margin.

The Rate Validator implements the margin validation formulas and rejection criteria. When Available_Margin is zero or negative, it rejects Rate Order creation. When individual Rate Komponen exceeds available margin, it rejects with the allowed maximum. When the sum of components exceeds available margin, it rejects with the shortfall amount. The component maintains audit history for minimum seven years as required by compliance.

The component exposes interfaces for payroll calculation to retrieve active component rates by order and component, for reporting to generate rate card summaries by client and period, and for administration to configure rates and approval workflows.

### Output Recording Component

The Output Recording component manages the 30-minute interval output system for Penjahit workers. The Output Recorder creates Output records with millisecond-precision UTC timestamps, validates submission timing against the current 30-minute interval, verifies Penjahit-Line assignment exists and is active, and marks records as SUBMITTED then PENDING_VERIFICATION.

The Output Validator calculates the current interval as floor(submission_time minutes / 30) times 30 minutes, validates submission_time falls within [Interval_Start, Interval_End), and rejects submissions outside valid intervals with helpful error messages showing current time, valid range, and next interval start. The Output Manager handles verification workflow transitions between SUBMITTED, VERIFIED, and REJECTED statuses, managing resubmission logic within the 7-day window and 3-attempt limit.

The Output Repository supports queries by Penjahit for earnings calculation, by Line and Station for supervisor dashboards, by status for verification queues, by interval for payroll locking, and by date range for reporting. The component integrates with the Payroll component to provide verified output for earnings calculation.

### Output Verification Component

The Output Verification component implements the Mandor and Kepala Produksi review workflow. The Verifier retrieves up to 100 pending SUBMITTED output records for assigned production lines, presenting them for approve or reject decisions. The Verification Handler processes verification decisions, transitioning status to VERIFIED for approvals and REJECTED for rejections with mandatory reason text between 10 and 500 characters.

The Notification Service sends alerts to Penjahit when output is rejected, handling delivery failures gracefully by logging the failure while still completing the status transition. The Audit Logger records verifier_id, ISO 8601 timestamp with second precision, and decision for every verification action, supporting compliance reporting and dispute resolution.

The component exposes the Verification Queue interface for supervisor dashboards, the Verification History interface for audit reports, and the Notification Status interface for delivery tracking.

### Quality Control Component

The Quality Control component manages the inspection workflow from pending output through final disposition. The QC Manager retrieves output records with QC Status PENDING and Output Status VERIFIED, presenting them for inspection. The QC Handler processes inspection results: PASS transitions Output to PASSED status and increments QC Bonus Pool counter, REPAIR transitions to REPAIR status and creates Repair record for the unit, SCRAP transitions to SCRAP status and records waste category.

The Defect Recorder captures defect categories for REPAIR and SCRAP results, requiring selection from the predefined taxonomy before submission. The QC Bonus Pool Manager tracks the pool counter across payroll periods, resetting on period close and distributing accumulated bonuses according to rules. The component integrates with the Repair Component to track repair workflows and with the Payroll Component to provide bonus pool totals.

The QC Repository supports queries by status for inspection queues, by defect category for quality reports, by period for bonus calculations, and by line for production monitoring. The component exposes interfaces for inspection screens, defect reports, and bonus distribution calculations.

### QC Bonus Pool Component

The QC Bonus Pool component manages the bonus accumulation and distribution system. The Bonus Calculator finalizes the pool on Saturday at midnight UTC+7, computing QC_Bonus_Pool as Passed_Units times QC_Bonus_Rate (fixed at Rp 500 per unit). It distributes the pool in equal shares to three recipients: two Mandor and one Kepala Operasional with active assignments during the period. If the pool is not evenly divisible by 3, floor division applies and the remainder carries to the first recipient.

The Bonus Distributor identifies eligible recipients based on active assignments during the payroll period, allocates shares, and generates the distribution report showing calculation formula, individual amounts, total pool, and remainder handling. The component generates alerts when recipient count differs from three, notifying Finance via system alert and email.

The component maintains the Bonus Pool Ledger recording pool accumulation by period, distribution transactions to each recipient, and remainder handling. The distribution report interface provides detailed breakdowns for Finance review and audit.

### Inventory Component

The Inventory component implements multi-tenant material tracking with client isolation. The Inventory Manager creates records on material receipt, tagging items with client_id and order_id, transitions status through the lifecycle: RAW to WIP on issue or cut, WIP to FINISHED on consumption, and enforces status sequence validation preventing invalid transitions.

The Client Isolator validates every material movement, comparing client_id of source material with client_id of destination order, rejecting movements that cross client boundaries with clear error messages. The Movement Recorder creates audit trail entries for RECEIVE, ISSUE, and TRANSFER movements, capturing movement_type, source_id, destination_id, quantity, timestamp, and actor_id.

The Inventory Repository supports queries by client_id for tenant isolation, by order_id for production material tracking, by status for warehouse management, by item_type for category reports, and by date range for movement history. The component exposes interfaces for warehouse operations, production issue tracking, and inventory audits.

### Payroll Component

The Payroll component calculates weekly worker earnings from verified output. The Payroll Manager closes payroll periods on Saturday at midnight UTC+7 (with ±1 minute tolerance), transitioning verified output to LOCKED status and preventing further modifications. The Payroll Calculator computes Base_Pay as sum of Verified_Output_Quantity times Rate_Komponen with two-decimal precision, handling missing rate components by exclusion with flagging in reports.

The Meal Allowance Calculator applies the configured percentage (default 0%) to Base_Pay, generating the Meal_Allowance amount. The QC Bonus Calculator distributes pool earnings to eligible workers based on their contribution proportion: Worker_QC_Contributions divided by Total_QC_Contributions times QC_Bonus_Pool. The component generates comprehensive Payroll Reports showing worker details, output summaries by order and component, Base_Pay, Meal_Allowance, QC_Bonus, and total earnings.

The Payable Generator creates payment records for each worker category: Penjahit receive Base_Pay plus Meal_Allowance plus QC_Bonus, Mandor receive their QC_Bonus_Share, and Kepala Operasional receive their QC_Bonus_Share. The component integrates with the Output component for verified output, Rate Card component for component rates, and QC Bonus Pool component for bonus calculations.

### Repair Component

The Repair Component manages the repair workflow for units with QC REPAIR disposition. The Repair Manager creates Repair records on REPAIR result with repair_reason_id, assigned_repairer_id, repair_deadline 48 hours from creation, and repair_attempt set to 1. The Repair Tracker counts repair attempts per unit, incrementing on each subsequent REPAIR result and resetting to 0 when QC result is PASS.

The Repair Handler processes completion events, transitioning repair status to COMPLETED and returning units to production queue. The Max Attempt Enforcer applies the 3-attempt limit, forcing SCRAP decision when a unit fails QC for the third time and transitioning status to MAX_ATTEMPTS_EXCEEDED. The component integrates with QC Component for re-inspection workflow and with Inventory Component for material consumption tracking.

The Repair Repository supports queries by status for repair queue management, by repairer_id for workload tracking, by deadline for overdue alerts, and by unit for repair history. The component exposes interfaces for repair assignment, completion tracking, and quality reports.

### Sample Management Component

The Sample Management Component handles the sample development workflow before order conversion. The Sample Manager initializes new samples with Draft status, transitions to Review on submission, and to Approved on client approval. The Sample Recorder maintains complete revision history for each sample, capturing actor_id, timestamp, changed_fields, and previous_values for every modification.

The Order Converter copies approved Sample data into Order fields on conversion and transitions Sample status to Converted. The Conversion Validator prevents modification of converted samples with clear error messages. The component integrates with Order Management for conversion and with Reporting for sample history.

The Sample Repository supports queries by status for workflow dashboards, by client for sample tracking, and by date range for history reports. The component exposes interfaces for sample creation, revision tracking, and order conversion.

### Line Management Component

The Line Management Component manages production line and station assignments. The Line Manager records Penjahit-Line assignments with start_time and optional end_time, allows rotation between Line and Station during production, validates overlapping assignments, and maintains allocation history for two years.

The Assignment Recorder captures penjahit_id, line_id, station_id, start_time, end_time, and actor_id for every assignment change. The History Keeper preserves allocation records for minimum two years supporting reporting requirements. The component integrates with Output Recording to associate output with correct line and station, and with Payroll to sum output across multiple assignments.

The Line Repository supports queries by Penjahit for assignment verification, by Line for supervisor views, by date range for history reports, and by active status for current assignments. The component exposes interfaces for assignment management, rotation tracking, and history reporting.

## Data Models

### Order Entity

The Order aggregate represents a manufacturing order from a client. It contains order_id (UUID primary key), client_id (foreign key to Client), scheme_type (enum: CMT, Semi-CMT, Full Package), order_number (unique string), description (text), expected_quantity (integer), current_quantity (integer), rate_order_id (foreign key to RateOrder), status (enum: Pending, Confirmed, In Production, QC Phase, Completed, Closed), created_at, updated_at, and deleted_at for soft deletes. The aggregate enforces scheme_type immutability after In Production status, validates status transitions match defined rules, and computes progress percentage from current versus expected quantity.

### Output Entity

The Output aggregate represents a piece-rate worker's production output. It contains output_id (UUID primary key), order_id (foreign key to Order), line_id (foreign key to Line), station_id (foreign key to Station), component_id (foreign key to RateKomponen), penjahit_id (foreign key to Worker), quantity (integer, 1-100), interval_start (datetime UTC), interval_end (datetime UTC), status (enum: SUBMITTED, VERIFIED, REJECTED), qc_status (enum: PENDING, PASSED, REPAIR, SCRAP), verification_id (foreign key to Verification), verification_time (datetime), rejection_reason (text), resubmission_count (integer 0-3), created_at, and updated_at. The aggregate validates quantity range, enforces resubmission limits, tracks verification and rejection history, and supports payroll locking on period close.

### QCRecord Entity

The QCRecord aggregate represents a quality control inspection result. It contains qc_record_id (UUID primary key), output_id (foreign key to Output), inspector_id (foreign key to Worker), qc_station_id (foreign key to QCStation), result (enum: PASS, REPAIR, SCRAP), defect_category_id (foreign key to DefectCategory, nullable), waste_category_id (foreign key to WasteCategory, nullable), repair_id (foreign key to Repair, nullable), inspection_time (datetime UTC), notes (text), and created_at. The aggregate requires defect category for REPAIR and SCRAP results, requires waste category for SCRAP results, and creates Repair records for REPAIR disposition.

### PayrollPeriod Entity

The PayrollPeriod aggregate represents a weekly payroll calculation period. It contains period_id (UUID primary key), start_date (date), end_date (date), status (enum: OPEN, CALCULATING, CLOSED), closed_at (datetime UTC), qc_bonus_pool (decimal), total_base_pay (decimal), total_meal_allowance (decimal), total_qc_bonus (decimal), generated_report_id (foreign key to PayrollReport), created_at, and updated_at. The aggregate enforces single OPEN period at a time, computes closing on Saturday midnight, and locks output records on close.

### PayrollEntry Entity

The PayrollEntry aggregate represents an individual worker's earnings for a payroll period. It contains entry_id (UUID primary key), period_id (foreign key to PayrollPeriod), worker_id (foreign key to Worker), base_pay (decimal), meal_allowance (decimal), qc_bonus (decimal), total_earnings (decimal), order_summary (JSON), output_count (integer), verified_output_ids (array of UUID), generated_at (datetime UTC), and created_at. The aggregate validates worker active status during period, computes total from components, and stores order-level breakdown for reporting.

### InventoryItem Entity

The InventoryItem aggregate represents a physical material item in the warehouse. It contains inventory_item_id (UUID primary key), client_id (foreign key to Client), order_id (foreign key to Order, nullable), item_type (string), quantity (decimal), unit (string), status (enum: RAW, WIP, FINISHED), location_id (foreign key to Location), received_at (datetime UTC), issued_at (datetime UTC), consumed_at (datetime UTC), and created_at. The aggregate enforces client isolation on movements, validates status sequence (RAW→WIP→FINISHED), and tracks movement history.

### InventoryMovement Entity

The InventoryMovement entity represents a material movement transaction. It contains movement_id (UUID primary key), inventory_item_id (foreign key to InventoryItem), movement_type (enum: RECEIVE, ISSUE, TRANSFER), source_id (foreign key to Location or InventoryItem), destination_id (foreign key to Location or InventoryItem), quantity (decimal), order_id (foreign key to Order, nullable), actor_id (foreign key to User), timestamp (datetime UTC), and created_at. The entity supports movement audit trails and client isolation validation.

### Repair Entity

The Repair entity represents a repair workflow instance. It contains repair_id (UUID primary key), output_id (foreign key to Output), qc_record_id (foreign key to QCRecord), repair_reason_id (foreign key to DefectCategory), assigned_repairer_id (foreign key to Worker), status (enum: PENDING, IN_PROGRESS, COMPLETED, MAX_ATTEMPTS_EXCEEDED), repair_attempt (integer), deadline (datetime UTC), completed_at (datetime UTC), created_at, and updated_at. The entity enforces the 3-attempt maximum, tracks deadlines for overdue alerts, and increments attempt counter on each REPAIR result.

### RateOrder Entity

The RateOrder aggregate represents a rate agreement for an order. It contains rate_order_id (UUID primary key), order_id (foreign key to Order), client_id (foreign key to Client), rate_order_number (unique string), rate_order_value (decimal), material_cost (decimal), overhead (decimal), profit (decimal), available_margin (decimal), status (enum: DRAFT, PENDING_APPROVAL, APPROVED, REJECTED), approved_by (foreign key to User), approved_at (datetime UTC), created_at, updated_at, and deleted_at. The aggregate computes available_margin, validates component rates against margin, and enforces approval workflow.

### RateKomponen Entity

The RateKomponen aggregate represents an individual component rate within a RateOrder. It contains rate_komponen_id (UUID primary key), rate_order_id (foreign key to RateOrder), component_name (string), rate_value (decimal), sequence_number (integer), status (enum: PENDING, ACTIVE, REJECTED), approved_by (foreign key to User), approved_at (datetime UTC), created_at, updated_at, and deleted_at. The aggregate enforces sequential approval, validates against available margin, and creates audit trail entries on change.

### Worker Entity

The Worker aggregate represents a factory worker with role-specific attributes. It contains worker_id (UUID primary key), user_id (foreign key to User), worker_number (unique string), role (enum: Penjahit, Mandor, Kepala Produksi, QC Inspector, Kepala Operasional), hourly_rate (decimal for time workers), piece_rate_default (decimal for Penjahit default), active (boolean), hire_date (date), termination_date (date, nullable), and created_at. The aggregate validates role-based permission sets and tracks employment status.

### Sample Entity

The Sample aggregate represents a product sample for client approval. It contains sample_id (UUID primary key), order_id (foreign key to Order, nullable), client_id (foreign key to Client), sample_number (unique string), description (text), status (enum: Draft, Review, Approved, Converted, Rejected), converted_order_id (foreign key to Order, nullable), created_by (foreign key to User), created_at, updated_at, and deleted_at. The aggregate enforces status transition rules, creates revision history on modification, and prevents modification after conversion.

### Line Entity

The Line aggregate represents a production line. It contains line_id (UUID primary key), line_number (string), station_count (integer), active (boolean), created_at, and updated_at. It contains embedded Station entities for station configuration.

### Station Entity

The Station value object within Line represents an individual work station. It contains station_id (UUID), station_number (string), line_id (foreign key to Line), active (boolean), and created_at.

### Allocation Entity

The Allocation entity represents a Penjahit's assignment to a line and station. It contains allocation_id (UUID primary key), penjahit_id (foreign key to Worker), line_id (foreign key to Line), station_id (foreign key to Station), start_time (datetime UTC), end_time (datetime UTC, nullable), actor_id (foreign key to User), created_at, and updated_at. The entity enforces temporal constraints and supports rotation during production.

### AuditLog Entity

The AuditLog entity records all system changes for compliance. It contains audit_id (UUID primary key), entity_type (string), entity_id (UUID), action (string), actor_id (foreign key to User), old_values (JSON), new_values (JSON), timestamp (datetime UTC), and created_at. The entity supports filtering by entity type, actor, date range, and action for audit reports.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Rate Card Consistency

**Formula**: `Rate_Order = Sum(Rate_Komponen) + Material_Cost + Overhead + Profit`

**Invariant**: `Available_Margin = Rate_Order - (Material_Cost + Overhead + Profit)`

For any RateOrder with status APPROVED, the sum of all ACTIVE RateKomponen values plus Material_Cost, Overhead, and Profit MUST equal Rate_Order value. Available_Margin MUST remain non-negative after all component validations. Any rate change MUST preserve this invariant.

**Validates: Requirements 2.2, 2.6**

### Property 2: QC Bonus Pool Distribution

**Formula**: `Individual_Bonus = QC_Bonus_Pool ÷ 3`

For any PayrollPeriod with QC Bonus Pool distribution, the sum of individual bonuses MUST equal QC_Bonus_Pool exactly. When distributed to exactly three recipients, each MUST receive exactly QC_Bonus_Pool divided by 3, with remainder handling documented. Recipient count MUST be validated and alerts generated when not equal to three.

**Validates: Requirements 7.2, 7.4**

### Property 3: Payroll Calculation Round Trip

**Invariant**: `Payroll_Report = Calculate_Payroll(Verified_Outputs) → Re-calculate_Payroll(Raw_Data)`

For any payroll calculation, re-calculation from source data MUST produce identical results. When verified output is unchanged, re-running payroll calculation MUST yield same totals, amounts, and payables. This property ensures deterministic calculation and data integrity.

**Validates: Requirements 8.2, 8.6**

### Property 4: Inventory Client Isolation

**Invariant**: `Material.client_id == Destination_Order.client_id`

For any inventory movement, client_id of the source material MUST equal client_id of the destination order. When inequality is detected, the issue MUST be rejected with isolation violation error. All accepted movements MUST satisfy this invariant.

**Validates: Requirements 5.3, 5.4**

### Property 5: Output Verification State Machine

**Invariant**: `Output.status ∈ {SUBMITTED, VERIFIED, REJECTED}`

**Transitions**: SUBMITTED → VERIFIED (Mandor approval), SUBMITTED → REJECTED (Mandor rejection), REJECTED → SUBMITTED (Penjahit resubmit)

All output records MUST have status from the allowed set. Status transitions MUST follow allowed paths only. Payroll calculation MUST include only VERIFIED output. Resubmission MUST respect the 7-day window and 3-attempt limit.

**Validates: Requirements 4.2, 4.4, 4.8**

### Property 6: QC Bonus Pool Increment on Pass

**Metamorphic Property**: `Pass_Count_New = Pass_Count_Old + 1`

When QC result is PASS, the QC Bonus Pool counter MUST increment by exactly 1. When QC result is REPAIR or SCRAP, the QC Bonus Pool counter MUST NOT change. This property ensures bonus accumulation matches actual passed units.

**Validates: Requirements 6.4, 6.9**

### Property 7: Sample to Order Conversion Preservation

**Invariant**: `Order_Data_Contains(Sample_Data)`

When a Sample is converted to Order, the Order MUST contain all component data from the Sample including specifications, quantities, and rates. The Order MUST reference the Sample revision that was converted. Sample revision history MUST be preserved and accessible.

**Validates: Requirements 9.4, 9.5**

### Property 8: Inventory Movement Audit Trail Completeness

**Invariant**: `Audit_Trail.Count == Inventory_Movement.Count`

For any inventory movement of type RECEIVE, ISSUE, or TRANSFER, exactly one audit trail entry MUST be created. For any audit trail entry, corresponding movement MUST exist in history. Client isolation validation MUST be performed for all movements and recorded in audit.

**Validates: Requirements 5.5, 5.6**

### Property 9: Meal Allowance Proportionality

**Formula**: `Meal_Allowance = Meal_Percentage × Base_Pay`

When Meal_Percentage is configured (default 0%), the calculated Meal_Allowance MUST equal Meal_Percentage times Base_Pay with two-decimal precision. When Base_Pay changes, Meal_Allowance MUST recalculate proportionally. This property ensures consistent allowance application.

**Validates: Requirements 8.4, 8.6**

### Property 10: 30-Minute Interval Boundary

**Invariant**: `Output.submission_time ∈ [Interval_Start, Interval_End)`

For any output submission, the timestamp MUST fall within exactly one 30-minute interval. Interval boundaries MUST be at 00:00, 00:30, 01:00, and so on. Output MUST be assigned to the correct interval based on submission time. No output can exist outside defined intervals.

**Validates: Requirements 3.2, 3.3, 3.5**

## Error Handling

### Validation Errors

The system implements layered validation with clear error messaging. Input validation errors occur when user-provided data fails format, range, or required field checks, returning HTTP 422 with field-specific error messages. Business rule violations occur when an operation violates domain constraints, returning HTTP 422 with human-readable explanation of the violation. Authorization errors occur when users attempt actions beyond their role permissions, returning HTTP 403 with required permission indication.

### Rate Card Validation Errors

When Available_Margin is zero or negative, the system returns error: "Rate Order cannot be created with negative or zero margin. Current margin: {value}". When Rate_Komponen exceeds available margin, the system returns error: "Component rate {name} of {value} exceeds available margin {margin}. Maximum allowed: {max}". When sum of components exceeds margin, the system returns error: "Total component rates {sum} exceed available margin {margin}. Shortfall: {shortfall}".

### Output Submission Errors

When submission time falls outside valid interval, the system returns error: "Submission time {time} is outside valid interval [{start}, {end}). Next interval starts at {next}". When Penjahit-Line assignment is invalid, the system returns error: "No active assignment found for Penjahit {id} on Line {id}. Please verify assignment before submitting output".

### Verification Errors

When attempting to verify without authorization, the system returns error: "Permission denied. Only Mandor or Kepala Produksi can verify output". When rejection reason is too short, the system returns error: "Rejection reason must be between 10 and 500 characters. Current length: {length}". When resubmission limit is exceeded, the system returns error: "Maximum resubmission attempts (3) exceeded for this output".

### QC Inspection Errors

When submitting REPAIR or SCRAP without defect category, the system returns error: "Defect category is required for REPAIR and SCRAP results". When submitting SCRAP without waste category, the system returns error: "Waste category is required for SCRAP results". When exceeds repair attempt limit, the system returns error: "Maximum repair attempts (3) exceeded. Unit has been automatically SCRAPed".

### Inventory Errors

When client isolation is violated, the system returns error: "Material from Client {id} cannot be issued to Order {id} (Client {order_client_id}). Client isolation violation prevented". When invalid status transition, the system returns error: "Invalid status transition from {current} to {requested}. Valid sequence: RAW → WIP → FINISHED".

### Integration Errors

Notification delivery failures are logged but do not block status transitions. Database constraint violations trigger transaction rollback with detailed error. External service timeouts use circuit breaker pattern with fallback behavior.

## Testing Strategy

### Dual Testing Approach

The testing strategy combines unit tests for specific examples and edge cases with property-based tests for universal properties across all inputs. Unit tests verify concrete behavior with representative examples, integration points between components, and specific error conditions. Property-based tests verify universal properties that hold for all inputs, providing comprehensive coverage through input randomization. Together they ensure complete correctness verification.

### Property-Based Testing Configuration

When property-based testing applies (pure functions with clear input/output behavior), the system uses a property-based testing library appropriate for PHP such as Eris or PHPUnit's data providers with Faker. Each property test runs minimum 100 iterations with randomized inputs covering edge cases. Test tagging follows format: **Feature: lukla-operations-engine, Property N: {property_description}**.

Property-based testing applies to: Rate card margin calculations (Property 1), QC bonus pool distribution (Property 2), Payroll calculation round trip (Property 3), Client isolation validation (Property 4), Output state machine transitions (Property 5), QC bonus increment logic (Property 6), Sample conversion preservation (Property 7), Audit trail completeness (Property 8), Meal allowance proportionality (Property 9), and 30-minute interval validation (Property 10).

### Unit Testing Focus Areas

Unit tests cover specific examples demonstrating correct behavior: Creating orders with each scheme type and verifying initialization, approving rate components sequentially and verifying status changes, submitting output at interval boundaries and verifying acceptance, verifying output at interval boundaries and verifying rejection, passing REPAIR and SCRAP with required categories, and rejecting REPAIR and SCRAP without required categories.

Unit tests also cover integration between components: Output verification triggering payroll eligibility, QC PASS triggering bonus pool increment, Payroll period close locking verified output, Sample conversion creating order with correct data, Inventory movement creating audit trail entries, and Repair workflow tracking attempt count.

### Edge Case Coverage

Unit tests address specific edge cases: Zero and negative margin rates, maximum component rates at margin boundary, output quantity at limits (1 and 100), submission exactly at interval boundaries, rejection reason at min and max length (10 and 500 characters), resubmission at limit boundary (3 attempts), QC bonus pool not evenly divisible by 3, workers with multiple line assignments, inventory at zero quantity, and samples with extensive revision history.

### Integration Testing Strategy

Integration tests verify end-to-end workflows: Complete order lifecycle from Pending to Closed, weekly payroll calculation with all component types, output submission through verification to payroll, QC inspection through bonus distribution, repair workflow from REPAIR to completion, and sample conversion with revision preservation.

Integration tests use in-memory databases with representative data sets, test isolation to prevent state leakage, and assertions on final state rather than implementation details.

### Test Data Management

Test fixtures provide reusable data: Sample clients with different scheme types, workers for each role (Penjahit, Mandor, Kepala Produksi, QC Inspector), sample rate cards with valid and invalid configurations, inventory items with various statuses, and sample output records at various stages.

### Continuous Integration

All tests run on every commit with blocking failure. Test coverage reports generated weekly with minimum 80% coverage target. Performance benchmarks run monthly to detect regression.

### Why Property-Based Testing Applies

Property-based testing applies to this feature because: The code under test involves pure functions with clear input/output behavior (rate calculations, bonus distribution, payroll computation), there are universal properties that hold across wide input ranges (margin consistency, interval boundaries, client isolation), the input space is large (rate values, timestamps, quantities, worker combinations), and tests would benefit from 100+ iterations exploring edge cases automatically.

Property-based testing does not apply to: Infrastructure configuration, UI rendering, or external service integration.