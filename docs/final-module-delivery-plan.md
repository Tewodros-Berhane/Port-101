# Final Module Delivery Plan (Roles, Flows, and Production Readiness)

_Last updated: 2026-02-21_

## 1) Goal

Define the final post-core execution plan so we can move from a working platform foundation into production-ready ERP modules with clear role architecture, module flows, and release gates.

This plan extends:

- `docs/post-core-implementation-plan.md`
- `docs/explanation.md`
- `docs/module-dependency.md`

## 2) External Benchmark Research (2026)

### What modern ERP systems consistently do

Based on official Odoo and Microsoft ERP documentation, production ERP systems converge on these essentials:

1. Strong role-based access plus record-level rules (not just page-level visibility).
2. State-driven workflows per module (quote, order, receive, post, reconcile).
3. Multi-company isolation with strict company context controls.
4. Cross-module handoffs via controlled workflow transitions/events.
5. Approval rules with thresholds and ordered approvers.
6. API-first integration surface with explicit versioning/deprecation policy.
7. Always-on auditability and reporting/export capability.

### Odoo patterns we should adopt

- Group access + record rules model for both capability and data visibility.
- Sales: quotation -> sales order -> delivery -> invoicing policy handoff.
- Inventory: one-step/two-step operations plus reordering rules.
- Purchasing: RFQ -> PO -> receipt -> vendor bill.
- Accounting: invoice posting, payments, reconciliation, sequences, taxes, fiscal controls.
- Multi-company guardrails throughout models and business logic.
- Approval rules with optional ordered/exclusive approvals.

### Odoo patterns we should not copy directly

- Very broad module coupling in UI navigation by default. We should keep tighter role-focused navigation for clarity.
- Excessive configuration exposure to non-admin users. We should hide advanced settings unless role requires it.

## 3) Role Architecture Decision

### Decision summary

We will use **module-scoped functional roles** on top of current owner/member membership, not one giant global role and not one role per screen.

### 3.1 Authorization model (4 layers)

1. Workspace guard layer:
- `super_admin` for platform routes.
- `company_user` for company routes.

2. Functional role bundles:
- Sales, inventory, purchasing, finance, approvals, reporting bundles.

3. Approval authority profile:
- Limits by amount/risk and module (for example purchase approvals up to threshold).

4. Data scope profile:
- `own_records`, `team_records`, `company_records`, `read_all`.

This prevents role explosion while still allowing precise access control.

### 3.2 Initial role catalog

| Role | Primary modules | Scope | Notes |
| --- | --- | --- | --- |
| Company Owner | All | company_records | Full company administration |
| Operations Admin | Sales, Inventory, Purchasing, Reports | company_records | Non-finance operational admin |
| Sales Manager | Sales, Reports | team_records/company_records | Quote/order control + team KPIs |
| Sales User | Sales | own_records | Pipeline/quote/order execution |
| Inventory Manager | Inventory, Reports | company_records | Warehouse, adjustments, transfers |
| Warehouse Clerk | Inventory | own_records/team_records | Receipts, picks, shipments |
| Purchasing Manager | Purchasing, Reports | company_records | RFQ/PO governance |
| Buyer | Purchasing | own_records/team_records | RFQ and PO execution |
| Finance Manager | Accounting, Reports | company_records | Posting, close controls, approvals |
| Accountant | Accounting | team_records/company_records | AR/AP operations, reconciliation |
| Approver | Approvals | company_records | Cross-module threshold approvals |
| Auditor | Reports, Audit | read_all | Read-only + exports |

### 3.3 Permission namespace model

Use consistent permission keys:

- `sales.leads.view`, `sales.quotes.manage`, `sales.orders.approve`
- `inventory.stock.view`, `inventory.moves.manage`, `inventory.adjustments.approve`
- `purchasing.rfq.manage`, `purchasing.po.approve`
- `accounting.invoices.post`, `accounting.payments.manage`, `accounting.period.close`
- `approvals.requests.manage`
- `reports.*`

Keep existing `core.*` permissions and map new modules similarly.

### 3.4 Segregation of duties (SoD) baseline

Minimum SoD checks for production readiness:

- Same user should not both create and final-approve high-value PO.
- Same user should not both create vendor and approve first payment.
- Finance posting/period close should require finance roles only.

## 4) Module Flow Blueprint (Detailed)

### 4.1 Sales (Lead to Cash entry)

### Entities

- `leads`, `opportunities`
- `sales_quotes`, `sales_quote_lines`
- `sales_orders`, `sales_order_lines`

### States

- Lead: `new -> qualified -> quoted -> won/lost`
- Quote: `draft -> sent -> approved/rejected -> confirmed`
- Order: `confirmed -> fulfilled -> invoiced -> closed`

### Role responsibilities

- Sales User: create/manage own leads, quotes, orders.
- Sales Manager: approve discount exceptions and confirm large quotes/orders.
- Finance roles: invoice/posting handoff after delivery/invoicing policy condition.

### Cross-module events

- `SalesOrderConfirmed` -> inventory reservation request.
- `SalesOrderReadyForInvoice` -> accounting invoice draft.

### 4.2 Inventory (Order to Fulfillment)

### Entities

- `warehouses`, `locations`
- `stock_moves`, `stock_move_lines`
- `stock_levels`, `stock_adjustments`

### States

- Move: `draft -> reserved -> picked -> packed -> done/cancelled`
- Receipt: `draft -> received -> quality_hold(optional) -> done`

### Role responsibilities

- Warehouse Clerk: execute receipts/picks/shipments.
- Inventory Manager: configure warehouses, approve adjustments/transfers.

### Cross-module events

- `StockDelivered` -> accounting marks invoice ready.
- `StockBelowReorderPoint` -> purchasing RFQ suggestion.

### 4.3 Purchasing (Procure to Pay entry)

### Entities

- `purchase_rfqs`, `purchase_rfq_lines`
- `purchase_orders`, `purchase_order_lines`
- `vendor_price_lists`

### States

- RFQ: `draft -> sent -> vendor_responded -> selected`
- PO: `draft -> approved -> ordered -> partially_received/received -> billed -> closed`

### Role responsibilities

- Buyer: create RFQs and POs.
- Purchasing Manager: approve POs above policy threshold.

### Cross-module events

- `PurchaseOrderApproved` -> expected receipt pipeline.
- `PurchaseReceiptCompleted` -> accounting vendor bill draft/match.

### 4.4 Accounting Lite (AR/AP + Reconciliation)

### Entities

- `invoices` (customer/vendor)
- `invoice_lines`
- `payments`
- `reconciliation_entries`

### States

- Invoice: `draft -> posted -> partially_paid/paid -> cancelled`
- Payment: `draft -> posted -> reconciled`

### Role responsibilities

- Accountant: draft/post invoices, apply payments, reconcile.
- Finance Manager: approve reversals, manage close controls.

### Controls

- Numbering sequences per document type.
- Tax period locking and close checks.
- Immutable audit trail for posted documents.

### 4.5 Approvals (Cross-module)

### Entities

- `approval_policies`
- `approval_requests`
- `approval_steps`

### Rules

- Threshold-based routing (amount, discount percent, risk flags).
- Ordered approvers for high-risk actions.
- Escalation timeout + reassignment.

### First approval policies to implement

- Sales discount over threshold.
- Purchase order over threshold.
- Manual stock adjustment above quantity/valuation threshold.
- Payment reversal/write-off above threshold.

### 4.6 Reports (Operational + Financial)

### Output formats

- PDF (branded Blade templates)
- XLSX (tabular exports)

### Initial report catalog

- Sales performance (quote conversion, order value, aged pipeline)
- Fulfillment performance (OTIF, pick/pack/delivery cycle)
- Purchasing performance (lead times, vendor reliability, spend)
- Finance snapshot (AR/AP aging, cash in/out, invoice/payment status)
- Governance/audit (approval turnaround, policy exceptions, audit events)

## 5) Delivery Sequence and Milestones

### Phase A: Role Architecture Expansion (foundation)

1. Seed functional roles and permission bundles.
2. Add data scope policy helpers (`own/team/company/read_all`).
3. Add approval authority profile model.
4. Add SoD checks for critical actions.

Exit criteria:

- Role assignment UI supports new roles.
- Permission tests pass for each role bundle.
- Route/policy tests pass for data-scope behavior.

### Phase B: Sales MVP

1. Leads/opportunities list + detail + create/edit.
2. Quotes with approval hooks.
3. Orders and order confirmation.
4. Event emission for inventory/accounting handoffs.

Exit criteria:

- End-to-end lead -> quote -> order works.
- Manager approvals enforced where configured.

### Phase C: Inventory MVP

1. Warehouse/location setup.
2. Stock levels + movement ledger.
3. Receipts/deliveries/transfers workflow.
4. Reservation and delivery events.

Exit criteria:

- Confirmed orders reserve and complete stock moves.
- Stock consistency tests pass.

### Phase D: Accounting Lite MVP

1. AR/AP invoice lifecycle.
2. Payments and reconciliation basics.
3. Tax period + numbering sequence enforcement.
4. Posting audit protections.

Exit criteria:

- Sales and purchase handoffs generate correct invoice/bill drafts.
- Reconciliation and aging reports are consistent.

### Phase E: Purchasing MVP

1. Vendor RFQ and comparison.
2. PO approvals and lifecycle.
3. Receipt and vendor bill matching handoff.

Exit criteria:

- RFQ -> PO -> receipt -> bill flow works end-to-end.

### Phase F: Approvals + Reporting hardening

1. Unified approvals queue UX.
2. PDF/XLSX report coverage expansion.
3. Scheduled exports and policy metrics.

Exit criteria:

- All critical workflows have approval and export coverage.

## 6) Production Readiness Gates

### Security and compliance

- 100% critical workflow authorization checks (policy + route + service).
- SoD rules active on high-risk actions.
- Audit logs for create/update/delete/post/approve/reject.

### Reliability

- Idempotent event handlers for cross-module workflows.
- Queue retry/dead-letter handling for notifications and exports.
- Backup/restore runbook for PostgreSQL and media attachments.

### Performance

- Query budgets for dashboard/report endpoints.
- Pagination and async exports for heavy reports.
- Index review for all foreign keys and high-cardinality filters.

### Observability

- Structured logs with request/user/company correlation IDs.
- Metrics: workflow latency, failed jobs, approval SLA, export durations.
- Alerting for job failures, backlog growth, and reconciliation errors.

### Testing

- Feature tests per end-to-end workflow.
- Role/permission matrix tests for every module.
- Contract tests for `/api/v1` endpoints.
- Long-running regression suite on PostgreSQL in CI/nightly.

## 7) Explicit Scope Decisions

- `APP_OWNERSHIP_MODE` runtime mode switching is explicitly out of scope for now.
- Focus is single deployed ownership pattern with strict role-based company/platform separation.

## 8) Immediate Next Build Order

1. Phase A (role architecture expansion).
2. Phase B (sales MVP) and Phase C (inventory MVP) in parallel where possible.
3. Phase D (accounting lite) once sales/inventory handoff events are stable.
4. Phase E (purchasing) then Phase F (approvals/reporting hardening).

## 9) References

- Odoo Access Rights: https://www.odoo.com/documentation/master/applications/general/users/access_rights.html
- Odoo Multi-company: https://www.odoo.com/documentation/19.0/applications/general/companies/multi_company.html
- Odoo Sales Quotations: https://www.odoo.com/documentation/19.0/applications/sales/sales/sales_quotations/create_quotations.html
- Odoo Invoicing Policy: https://www.odoo.com/documentation/19.0/applications/sales/sales/invoicing/invoicing_policy.html
- Odoo Inventory One-step Flows: https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/one_step.html
- Odoo Reordering Rules: https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/warehouses_storage/replenishment/reordering_rules.html
- Odoo Purchase RFQ: https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/purchase/manage_deals/rfq.html
- Odoo Purchase to Vendor Bill: https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/purchase/manage_deals/manage.html
- Odoo Customer Invoices: https://www.odoo.com/documentation/19.0/applications/finance/accounting/customer_invoices.html
- Odoo Vendor Bills: https://www.odoo.com/documentation/19.0/applications/finance/accounting/vendor_bills.html
- Odoo Payments: https://www.odoo.com/documentation/19.0/applications/finance/accounting/payments.html
- Odoo Taxes: https://www.odoo.com/documentation/19.0/applications/finance/accounting/taxes.html
- Odoo Fiscal Year: https://www.odoo.com/documentation/19.0/applications/finance/accounting/reporting/year_end/fiscal_year.html
- Odoo Approval Rules: https://www.odoo.com/documentation/19.0/applications/studio/approval_rules.html
- Odoo External API (JSON-2): https://www.odoo.com/documentation/19.0/developer/reference/external_api.html
- Odoo AI Integrations: https://www.odoo.com/documentation/19.0/applications/general/integrations/ai.html
- Dynamics 365 Role-based Security: https://learn.microsoft.com/en-us/dynamics365/fin-ops-core/dev-itpro/sysadmin/role-based-security
- Dynamics 365 Role Hierarchy Guidance: https://learn.microsoft.com/en-us/dynamics365/guidance/implementation-guide/security-modeling-role-hierarchy
