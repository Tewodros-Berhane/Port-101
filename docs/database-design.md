# Database Design (Current + Module Needs)

## Current Tables (Core)

These tables already exist from the core platform foundation.

### Identity & Access

- **users**: authentication + profile fields
- **roles**: role definitions (global or company‑scoped)
- **permissions**: permission definitions
- **role_permissions**: role ↔ permission mapping
- **company_users**: user membership in companies with role assignment

### Company & Context

- **companies**: company profile and ownership

### System Tables

- **sessions**, **password_reset_tokens**, **cache**, **jobs**, **failed_jobs**

## Module Table Needs

Below are the tables each module will require when implemented.

### 1) Sales Module

Purpose: lead → quote → sales order

Tables:

- **sales_leads**
- **sales_opportunities**
- **sales_quotes**
- **sales_quote_lines**
- **sales_orders**
- **sales_order_lines**
- **sales_order_statuses** (optional enum table)

Depends on core tables:

- **partners** (customers)
- **products**
- **taxes**
- **currencies**
- **users** (assigned reps)

### 2) Inventory Module

Purpose: stock tracking and fulfillment

Tables:

- **warehouses**
- **stock_locations**
- **stock_moves**
- **stock_reservations**
- **stock_adjustments**
- **stock_lots** (optional)
- **stock_serials** (optional)

Depends on core tables:

- **products**
- **uoms**
- **companies**
- **users** (created/handled by)

Depends on Sales:

- **sales_orders** and **sales_order_lines** (for reservations and deliveries)

### 3) Purchasing Module

Purpose: RFQ → PO → receipt

Tables:

- **purchase_rfqs**
- **purchase_rfq_lines**
- **purchase_orders**
- **purchase_order_lines**
- **vendor_bills**

Depends on core tables:

- **partners** (vendors)
- **products**
- **taxes**
- **currencies**
- **users**

Depends on Inventory:

- **stock_moves** (receipts)

### 4) Accounting Module

Purpose: invoices, payments, ledger

Tables:

- **accounts**
- **journals**
- **ledger_entries**
- **invoices** (AR/AP)
- **invoice_lines**
- **payments**
- **reconciliations**

Depends on core tables:

- **partners**
- **taxes**
- **currencies**
- **users**

Depends on Sales:

- **sales_orders** (invoice generation)

Depends on Purchasing:

- **purchase_orders** (vendor bills)

### 5) Projects & Services

Purpose: time tracking and service billing

Tables:

- **projects**
- **project_tasks**
- **timesheets**
- **service_invoices** (or reuse invoices)

Depends on core tables:

- **partners**
- **users**

Depends on Accounting:

- **invoices** (billing)

### 6) Reporting Module

Purpose: analytics and dashboards

Tables:

- **report_schedules**
- **report_exports**
- **dashboards** (optional saved views)

Depends on all core + modules for data.

## Core Master Data Tables (Planned)

These are part of core but not yet built in full.

- **partners** (customers/vendors)
- **contacts**
- **addresses**
- **products**
- **uoms**
- **taxes**
- **currencies**
- **price_lists** (optional)

## Notes

- All tables should include `company_id`, `created_by`, `updated_by`, timestamps, and soft deletes where applicable.
- All tables should use UUID primary keys.
