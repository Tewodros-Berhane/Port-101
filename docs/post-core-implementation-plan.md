# Post-Core Implementation Plan (Role-Based)

## Purpose

Define the implementation plan after the Core/Platform is complete. This plan is organized by user roles and the pages they need, then mapped into phased module delivery.

## Assumptions (Core Complete)

- Auth, RBAC, company scoping, and audit foundations exist.
- Master data exists: partners, products, taxes, currencies, UoM.
- Inertia + React app shell is stable.

## Guiding Principles

- Build by **role workflows** first, then expand modules.
- Keep each module self-contained with shared kernel dependencies.
- Reuse common UI patterns documented in `docs/common-design-patterns.md`.
- Ship thin vertical slices (end-to-end flows) rather than wide partial modules.

## Role-Based Page Map (What Users Need)

### 1) Owner / Admin

Primary: configure the company and monitor all operations.

Pages:

- Dashboard (global KPIs)
- Company settings (profile, fiscal year, currency defaults)
- Users & roles
- Master data management
- Audit log

### 2) Sales User

Primary: lead to quote to order, then invoice.

Pages:

- Leads / Opportunities
- Quotes
- Sales Orders
- Customers (partners)
- Sales reports

### 3) Inventory / Warehouse

Primary: accurate stock and fulfillment.

Pages:

- Stock levels
- Warehouses / locations
- Receipts
- Deliveries
- Transfers

### 4) Purchasing

Primary: procure goods and manage vendors.

Pages:

- Vendors
- RFQs
- Purchase Orders
- Receipts (shared with inventory)
- Spend report

### 5) Finance / Accounting

Primary: invoicing, payments, and financial reporting.

Pages:

- Invoices (AR/AP)
- Payments & reconciliation
- Journals / ledger
- Financial statements (P&L, balance sheet)

### 6) Manager / Approver

Primary: approve and monitor.

Pages:

- Approvals queue
- Team KPIs
- Exceptions report

### 7) Auditor (Read-Only)

Primary: access and exports.

Pages:

- Audit log
- Reports
- Record read-only views

## Module Delivery Phases

### Phase 1: Revenue + Fulfillment (Sales + Inventory + Accounting Lite)

Goal: Quote -> Order -> Delivery -> Invoice

Deliverables:

- Sales module: leads, quotes, orders
- Inventory module: stock, reservations, deliveries
- Accounting lite: invoices + payments
- Role dashboards for Sales, Inventory, Finance

### Phase 2: Procure to Pay (Purchasing + AP)

Goal: RFQ -> PO -> Receipt -> Vendor bill

Deliverables:

- Purchasing module: vendor, RFQ, PO
- AP flows and vendor bills
- Approval rules for purchases

### Phase 3: Reporting + Governance Expansion

Goal: operational visibility and compliance

Deliverables:

- Reporting: financial + operational dashboards
- Audit/approval extensions
- Export and scheduled reports

### Phase 4: Services + Projects

Goal: service-based operations

Deliverables:

- Projects, tasks, timesheets
- Service billing

### Phase 5: Optional Verticals

Goal: modular add-ons

Deliverables:

- Light manufacturing
- POS
- eCommerce integrations

## Implementation Breakdown (By Role)

### Sales Workflow Slice (Phase 1)

- Pages: Lead list -> Quote -> Sales Order
- Actions: convert lead, approve quote, confirm order
- Events: order confirmed triggers inventory reservation
- UI: standard list/detail/create patterns

### Inventory Workflow Slice (Phase 1)

- Pages: Stock levels, Receipts, Deliveries
- Actions: receive stock, reserve, ship
- Events: delivery triggers invoice readiness

### Accounting Workflow Slice (Phase 1)

- Pages: Invoices, Payments
- Actions: post invoice, apply payment, reconcile

### Purchasing Workflow Slice (Phase 2)

- Pages: RFQ -> PO -> Receipt -> Vendor Bill
- Actions: approve PO, receive, match invoice

## Shared Components to Build Post-Core

- Data tables with filters and saved views
- Status badges, activity timeline
- Approval widgets
- KPI cards and quick actions
- Printable/exportable report layouts

## Technical Workstreams

### 1) Domain Models and Migrations

Define entities per module with UUIDs and company scoping.

### 2) Services and Workflows

Create domain services for each workflow with events.

### 3) UI Pages and Navigation

Add role-based nav sections and consistent layouts.

### 4) APIs and Integrations

Expose `/api/v1` for partners/products/orders/invoices.

### 5) Reporting

Build reusable report components and export formats.

## Testing Strategy

- Feature tests per workflow (quote->order->invoice)
- Permission tests by role
- Reporting accuracy tests

## Risks

- Scope creep: enforce phase boundaries.
- Accounting complexity: deliver core invoicing first.
- Workflow coupling: keep module boundaries strict.

## Open Decisions

- Which role gets the first dashboard focus
- Approval policy defaults by role
- Initial industry vertical to optimize workflows
