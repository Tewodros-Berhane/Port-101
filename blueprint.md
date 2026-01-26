# ERP Implementation Blueprint (Laravel)

## Birdseye View

Build a modular Laravel app where each ERP module is self-contained but plugs into a shared kernel. Start with three modules and a stable foundation for adding more later.

Initial modules for this blueprint:

- Sales
- Inventory
- Accounting

## Project Structure (Modular Monolith)

```
app/
  Core/
    Auth/
    Company/
    Events/
    Policies/
    Support/
    Traits/
  Modules/
    Sales/
    Inventory/
    Accounting/
bootstrap/
config/
database/
  migrations/
  seeders/
resources/
  views/
  js/
routes/
storage/
tests/
```

## Module Layout (Example)

```
app/Modules/Sales/
  Http/
    Controllers/
    Requests/
  Domain/
    Models/
    Services/
    Actions/
    ValueObjects/
  Policies/
  Events/
  Jobs/
  Observers/
  Providers/
    SalesServiceProvider.php
  routes/
    web.php
    api.php
  database/
    migrations/
    seeders/
  resources/
    views/
    js/
```

Each module owns its:

- routes, controllers, requests
- domain models and services
- migrations and seeders
- policies and permissions
- UI assets and views

## Shared Kernel (app/Core)

The core layer provides shared building blocks:

- Auth, RBAC, permissions
- Company and multi-company scoping
- Base entities (Product, Partner)
- Audit logging
- Events and notifications
- Attachment system

Core database tables:

- companies, users, roles, permissions
- partners (customers/vendors), contacts, addresses
- products, uom, taxes, price_lists
- audit_logs, attachments

## Module Blueprint (3 Modules)

### 1) Sales Module

Purpose: lead to quote to sales order

Key tables:

- sales_leads
- sales_quotes
- sales_orders
- sales_order_lines

Core flows:

- Lead -> Quote -> Sales Order
- Sales Order confirmation triggers inventory reservation
- Optional quote approval based on amount

### 2) Inventory Module

Purpose: stock tracking and fulfillment

Key tables:

- warehouses
- stock_locations
- stock_moves
- stock_reservations

Core flows:

- Stock receipt
- Stock reservation from sales order
- Stock delivery to customer

### 3) Accounting Module

Purpose: core ledger and invoices

Key tables:

- accounts
- journals
- ledger_entries
- invoices
- payments

Core flows:

- Invoice creation from sales order
- Posting invoice creates ledger entries
- Payment applied and reconciled

## Cross-Module Integration

Use events and actions to keep modules decoupled:

- SalesOrderConfirmed -> InventoryReserveStock
- SalesOrderConfirmed -> AccountingCreateInvoiceDraft
- InvoicePosted -> AccountingPostLedgerEntries
- StockDelivered -> AccountingMarkInvoiceReady

Events are placed in app/Core/Events or module Events, and handled by module listeners.

## Route and UI Strategy

- Each module registers routes via its ServiceProvider
- UI uses shared layout and module-specific pages
- API endpoints live under /api and mirror web actions

## Implementation Plan (High Level)

1. Foundation
    - Core auth, RBAC, company scoping, base entities
2. Sales module (minimal)
    - Leads, quotes, sales orders
3. Inventory module (minimal)
    - Stock, reservations, deliveries
4. Accounting module (minimal)
    - Accounts, invoices, ledger
5. Cross-module events and workflows

## Sample Request Flow (Sales Order to Delivery)

1. SalesController@confirm
2. SalesService confirms order and emits SalesOrderConfirmed
3. Inventory listener reserves stock
4. Accounting listener creates invoice draft
5. Delivery action emits StockDelivered
6. Accounting marks invoice ready or posts

## Authorization and Multi-Company

- Policies enforce access by company and module permissions
- All module queries scoped by company_id
- Shared policies live in app/Core/Policies

## Testing Approach

- Unit tests per module domain services
- Feature tests for key workflows
- Seeders for consistent demo data

## Future Scaling Path

- Add new module under app/Modules
- Register module via provider
- Add module metadata and dependencies
- Optional: extract heavy modules into services later
