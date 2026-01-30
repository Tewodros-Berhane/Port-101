# Module Dependency Map

## Purpose

Define how modules depend on each other and which tables are shared across modules.

## Core Platform (Foundation)

All modules depend on Core.

Core provides:

- Auth + RBAC: users, roles, permissions
- Company scoping: companies, company_users
- Master data: partners, products, taxes, currencies, uoms
- Audit and settings (planned)

## Dependency Graph (High-Level)

- **Core** → required by all modules
- **Sales** → depends on Core
- **Inventory** → depends on Core + Sales
- **Purchasing** → depends on Core + Inventory
- **Accounting** → depends on Core + Sales + Purchasing
- **Projects/Services** → depends on Core + Accounting
- **Reporting** → depends on all modules

## Detailed Dependencies

### Sales Module

Depends on Core tables:

- partners, products, taxes, currencies, users

Provides tables to:

- Inventory (orders and lines for reservation)
- Accounting (order → invoice)

### Inventory Module

Depends on Core tables:

- products, uoms, companies, users

Depends on Sales tables:

- sales_orders, sales_order_lines

Provides tables to:

- Purchasing (receipts)
- Accounting (delivery confirmation)

### Purchasing Module

Depends on Core tables:

- partners (vendors), products, taxes, currencies, users

Depends on Inventory tables:

- stock_moves (receipts)

Provides tables to:

- Accounting (vendor bills)

### Accounting Module

Depends on Core tables:

- partners, taxes, currencies, users

Depends on Sales tables:

- sales_orders (AR invoice generation)

Depends on Purchasing tables:

- purchase_orders (AP bills)

Provides tables to:

- Projects/Services (service billing)

### Projects & Services

Depends on Core tables:

- partners, users

Depends on Accounting tables:

- invoices

### Reporting Module

Depends on all module tables + core tables for data aggregation.

## Cross-Module Events (Planned)

- SalesOrderConfirmed → InventoryReserveStock
- SalesOrderConfirmed → AccountingCreateInvoiceDraft
- StockDelivered → AccountingMarkInvoiceReady
- PurchaseOrderReceived → AccountingCreateVendorBill

## Notes

- Modules must not directly update each other’s tables (only through events/services).
- Shared read access is allowed via domain services.
