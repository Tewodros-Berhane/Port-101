# Implementation Plan (Post-Phase 7 Delivery)

_Last updated: 2026-03-23_

## 1) Purpose

Define the remaining implementation plan for Port-101 after completion of:

- core platform
- Sales, Inventory MVP, Purchasing MVP, Accounting foundations
- Approvals and Reports
- Projects/Services
- API v1 Phases 0 through 7, including outbound webhooks

This document covers the remaining work required to finish the current planned delivery properly before moving into optional product expansion.

## 2) What Is Already Done

The following are already implemented and should not be replanned here:

- company/platform separation
- auth, RBAC, company scoping, settings, attachments, notifications, audit logs
- Sales, Inventory MVP, Purchasing, Accounting, Approvals, Reports
- Projects/Services including recurring billing and invoice handoff
- API v1 for core module resources
- outbound webhook foundation and first production event set

This plan starts from that baseline.

## 3) Remaining Delivery Tracks

The remaining work is grouped into four tracks:

1. Webhook workspace and delivery operations UX
2. Advanced inventory depth
3. Integration hardening
4. Production hardening and release readiness

These are the last major tracks required to call the current planned delivery complete.

## 4) Recommended Execution Order

### Phase 1: Webhook Operations UX

Build company-side management pages and webhook delivery operations visibility so integrations are usable by real customers, not just through API calls.

### Phase 2: Advanced Inventory Depth

Close the biggest remaining ERP capability gap with:

- lots and serials
- cycle counts
- reordering rules
- kits and bundles

### Phase 3: Integration Hardening

Stabilize the API/webhook contract for third-party use:

- idempotency
- external references
- webhook governance/security hardening
- API lifecycle policy

### Phase 4: Production Hardening

Add operational readiness:

- observability
- queue failure tooling
- dead-letter visibility
- backup/recovery guidance
- performance review
- alerting
- long-running CI/nightly verification

## 5) Track A: Webhook Workspace And Delivery Operations UX

## Goal

Expose webhook management and delivery visibility in the company workspace so a company admin can configure, inspect, test, and recover integrations without using raw API endpoints.

## 5.1 Scope

Implement:

- webhook endpoint CRUD UI
- endpoint secret rotation UI
- test-delivery UI
- delivery history UI
- dead-letter visibility
- retry actions
- delivery metrics and trend cards

Out of scope in this track:

- inbound webhook receivers
- third-party connector catalogs
- full marketplace/integration app store

## 5.2 Information Architecture

Add a company workspace area:

- `/company/integrations`
- `/company/integrations/webhooks`

Suggested pages:

- `resources/js/pages/integrations/index.tsx`
- `resources/js/pages/integrations/webhooks/index.tsx`
- `resources/js/pages/integrations/webhooks/create.tsx`
- `resources/js/pages/integrations/webhooks/edit.tsx`
- `resources/js/pages/integrations/webhooks/show.tsx`

Suggested sidebar placement:

- top-level item: `Integrations`
- child items:
  - `Webhooks`
  - later `API Tokens`
  - later `Connectors`

## 5.3 Backend Work

### Routes

Create a new route file:

- `routes/moduleroutes/integrations.php`

Suggested route set:

- `GET /company/integrations`
- `GET /company/integrations/webhooks`
- `GET /company/integrations/webhooks/create`
- `POST /company/integrations/webhooks`
- `GET /company/integrations/webhooks/{endpoint}`
- `GET /company/integrations/webhooks/{endpoint}/edit`
- `PATCH /company/integrations/webhooks/{endpoint}`
- `DELETE /company/integrations/webhooks/{endpoint}`
- `POST /company/integrations/webhooks/{endpoint}/rotate-secret`
- `POST /company/integrations/webhooks/{endpoint}/test`
- `POST /company/integrations/webhooks/deliveries/{delivery}/retry`

### Controllers

Add:

- `app/Http/Controllers/Integrations/IntegrationsDashboardController.php`
- `app/Http/Controllers/Integrations/WebhookEndpointsController.php`
- `app/Http/Controllers/Integrations/WebhookDeliveriesController.php`

Controllers should reuse the existing webhook services rather than duplicating logic.

### Requests

Add:

- `app/Http/Requests/Integrations/WebhookEndpointStoreWebRequest.php`
- `app/Http/Requests/Integrations/WebhookEndpointUpdateWebRequest.php`

These can wrap or mirror the existing API validation rules.

### Policies / Permissions

Reuse:

- `integrations.webhooks.view`
- `integrations.webhooks.manage`

No new permission namespace is required unless an analytics-only role is added later.

## 5.4 UI Requirements

### Webhooks index page

Show:

- endpoint name
- target URL
- active/inactive state
- subscribed events
- last success
- last failure
- deliveries count
- latest delivery status

Actions:

- create
- edit
- rotate secret
- test
- open details
- delete

### Webhook details page

Show:

- endpoint metadata
- event subscriptions
- secret preview and last rotation timestamp
- delivery summary cards
- recent delivery table
- dead-letter / failed deliveries section

Actions:

- retry failed/dead
- test endpoint
- rotate secret
- pause/activate endpoint

### Delivery analytics panel

Show at minimum:

- deliveries last 24h / 7d / 30d
- success rate
- failed rate
- dead-letter count
- median delivery duration
- top failing event types
- top failing endpoints

## 5.5 Data / Schema Enhancements

The existing webhook tables are enough for the first UI pass, but this track should add:

- `webhook_endpoints.secret_rotated_at` nullable timestamp
- `webhook_endpoints.last_delivery_at` nullable timestamp
- `webhook_deliveries.dead_lettered_at` nullable timestamp
- optional `webhook_deliveries.first_attempt_at` nullable timestamp

If we want a full audit history for secret rotation, add:

- `webhook_secret_rotations`

Columns:

- `id`
- `company_id`
- `webhook_endpoint_id`
- `rotated_by`
- `rotated_at`
- `secret_preview_before`
- `secret_preview_after`

## 5.6 Testing

Add feature coverage for:

- web page access by owner, operations admin, auditor
- endpoint CRUD from web routes
- secret rotation from web UI
- test delivery action
- failed/dead delivery retry
- company scoping for endpoint and delivery pages

Add UI regression coverage where appropriate for:

- delivery-status badge rendering
- dead-letter section visibility

## 5.7 Exit Criteria

- company admins can fully manage webhook endpoints from the UI
- failed/dead deliveries are visible and retryable from the UI
- delivery analytics show meaningful operational insight
- no API-only path is required for routine webhook operations

## 6) Track B: Advanced Inventory Depth

## Goal

Close the main remaining ERP functional gap by extending inventory from basic stock movement into traceability, replenishment, audit counts, and product-structure support.

## 6.1 Delivery Order

Implement advanced inventory in this order:

1. lots and serial numbers
2. cycle counts
3. reordering rules
4. kits and bundles

That order is important because:

- traceability must exist before deeper counting and fulfillment logic
- stock accuracy should exist before automated replenishment
- kit behavior should sit on top of stable stock/reservation rules

## 6.2 Lots And Serial Numbers

## Goal

Support item traceability for stock-tracked products across receipts, internal moves, and deliveries.

### Schema

Add:

- `inventory_lots`
- `inventory_stock_move_lines`

Suggested `inventory_lots` columns:

- `id`
- `company_id`
- `product_id`
- `lot_number`
- `serial_number` nullable
- `tracking_type` (`lot`, `serial`)
- `manufactured_at` nullable
- `expires_at` nullable
- `status` (`active`, `consumed`, `expired`, `blocked`)
- `created_by`, `updated_by`
- timestamps

Suggested `inventory_stock_move_lines` columns:

- `id`
- `company_id`
- `stock_move_id`
- `product_id`
- `lot_id` nullable
- `quantity`
- `source_location_id` nullable
- `destination_location_id` nullable
- `created_by`, `updated_by`
- timestamps

### Product additions

Add to products:

- `tracking_mode` (`none`, `lot`, `serial`)

### Backend services

Add:

- `InventoryLotService`
- `InventoryTraceabilityService`
- extend `InventoryStockWorkflowService`

Responsibilities:

- generate/assign lots on receipts
- require serial assignment for serial-tracked products
- split delivery/transfer move lines by lot or serial
- validate availability at lot level
- maintain traceability queries

### UI

Add pages:

- `resources/js/pages/inventory/lots/index.tsx`
- `resources/js/pages/inventory/lots/show.tsx`

Add move-line assignment UI to:

- receipts
- deliveries
- transfers

### Reports

Add:

- lot traceability report
- expiring lots report
- serial movement history

### Tests

Cover:

- receipt with lot creation
- receipt with serial assignment
- delivery of lot-tracked goods
- delivery rejection when serial assignment is incomplete
- lot-level availability checks

### Exit criteria

- tracked products cannot move without required lot/serial detail
- full lot/serial history is queryable from the UI

## 6.3 Cycle Counts

## Goal

Support periodic stock verification and controlled adjustment posting.

### Schema

Add:

- `inventory_cycle_counts`
- `inventory_cycle_count_lines`

Suggested workflow:

- `draft -> in_progress -> reviewed -> posted -> cancelled`

### Backend services

Add:

- `InventoryCycleCountService`

Responsibilities:

- create count sessions by warehouse/location/category/product
- freeze expected quantities at count start
- record counted quantity
- compute variance
- require approval thresholds for high-variance adjustments
- generate adjustment stock moves on post

### UI

Pages:

- `resources/js/pages/inventory/cycle-counts/index.tsx`
- `resources/js/pages/inventory/cycle-counts/create.tsx`
- `resources/js/pages/inventory/cycle-counts/show.tsx`

Show:

- expected qty
- counted qty
- variance
- variance value
- approval state

### Integration

Tie into Approvals for:

- high-value variance
- high-quantity variance

### Tests

Cover:

- cycle count creation
- count line variance calculation
- approval-gated posting
- generated adjustment move correctness

### Exit criteria

- stock corrections happen through auditable count sessions, not ad hoc data edits

## 6.4 Reordering Rules

## Goal

Support replenishment planning from stock position rules.

### Schema

Add:

- `inventory_reorder_rules`
- optional `inventory_replenishment_suggestions`

Suggested `inventory_reorder_rules` columns:

- `id`
- `company_id`
- `product_id`
- `location_id`
- `min_quantity`
- `max_quantity`
- `reorder_quantity`
- `lead_time_days` nullable
- `is_active`
- `created_by`, `updated_by`
- timestamps

### Backend services

Add:

- `InventoryReorderService`

Responsibilities:

- compute projected available quantity
- identify stock below threshold
- generate replenishment suggestions
- optionally convert suggestions into draft purchase RFQs later

### Jobs / commands

Add:

- `inventory:reorder-scan`

Run daily or more frequently.

### UI

Pages:

- `resources/js/pages/inventory/reordering/index.tsx`
- `resources/js/pages/inventory/reordering/rules.tsx`

Show:

- active rules
- current stock
- projected stock
- suggested reorder qty
- vendor/replenishment status when Purchasing integration exists

### Integration

This should integrate with Purchasing by creating:

- draft RFQ suggestions
- later auto-populated RFQ lines

### Tests

Cover:

- rule evaluation
- projected availability
- suggestion generation
- duplicate-suggestion suppression

### Exit criteria

- below-threshold stock is surfaced automatically and can feed procurement

## 6.5 Kits And Bundles

## Goal

Support non-manufacturing product structures such as sales kits and bundled stock compositions.

### Schema

Add:

- `product_bundles`
- `product_bundle_components`

Suggested bundle modes:

- `sales_only`
- `stocked_bundle`

### Backend services

Add:

- `InventoryBundleService`
- extend Sales/Inventory handoff services

Responsibilities:

- explode sales kit into component demand
- reserve/deliver components correctly
- keep commercial parent line visible to sales/accounting
- optionally support stocked bundles later if needed

### UI

Add product configuration UI:

- bundle flag
- component lines
- component quantities

### Tests

Cover:

- sales order with kit product
- inventory reservation of component lines
- delivery completion against components
- invoice remains tied to sold bundle line, not raw components

### Exit criteria

- kits work commercially and operationally without breaking stock truth

## 7) Track C: Integration Hardening

## Goal

Make API v1 and webhook delivery stable enough for real external consumers.

## 7.1 Idempotency Keys

### Goal

Prevent duplicate side effects from retried client requests.

### Scope

Add idempotency support to write-heavy endpoints:

- sales confirm/create actions
- invoice/payment post actions
- report export creation
- webhook retry/test creation where relevant

### Schema

Add:

- `api_idempotency_keys`

Columns:

- `id`
- `company_id`
- `user_id`
- `key`
- `request_fingerprint`
- `response_status`
- `response_body`
- `resource_type` nullable
- `resource_id` nullable
- `expires_at`
- timestamps

### Backend

Add:

- `IdempotencyService`
- `RequireIdempotency` middleware for selected routes

Behavior:

- repeated same key + same fingerprint returns stored response
- repeated same key + different fingerprint returns `409`

### Tests

Cover:

- duplicate POST returns cached response
- conflicting payload with same key returns error

## 7.2 External Reference Fields

### Goal

Allow external systems to map their records to Port-101 records cleanly.

### Scope

Add optional `external_reference` fields to integration-facing tables first:

- partners
- products
- sales orders
- purchase orders
- accounting invoices
- projects

### Rules

- uniqueness should be per company per entity type
- do not require one global external reference namespace across all modules

### Backend

Update:

- migrations
- request validation
- API resources
- search/filter support

### Tests

Cover:

- uniqueness within company
- search by external reference
- API create/update with external reference

## 7.3 Webhook Security And Governance Hardening

### Goal

Tighten delivery security and operational controls.

### Additions

- secret rotation history table and UI visibility
- webhook replay-window documentation and validation guidance
- timestamp tolerance policy
- header verification documentation
- optional endpoint pause after repeated dead letters

### Future optional enhancement

Add replay protection storage for inbound webhook receivers later, not now.

## 7.4 API Versioning And Deprecation Policy

### Goal

Make `API v1` lifecycle explicit before external adoption broadens.

### Deliverables

- `docs/api_v1.md` lifecycle section refresh
- response headers:
  - `X-API-Version`
  - optional `Deprecation`
  - optional `Sunset`
- internal changelog format for API-breaking vs non-breaking changes

### Optional later

- `/api/v1/meta`
- `/api/v1/changelog`

## 7.5 Optional Later: Inbound Webhooks / Connectors

Do not build this before the outbound integration layer and hardening work are stable.

Later candidates:

- inbound order creation
- inbound payment notifications
- eCommerce order sync
- shipping status callbacks

## 8) Track D: Production Hardening And Release Readiness

## Goal

Make the system operable in production, not just functionally complete.

## 8.1 Structured Logs And Correlation IDs

### Goal

Trace every request, job, and integration event across the stack.

### Deliverables

- request correlation ID middleware
- queue job correlation propagation
- structured log context:
  - request ID
  - user ID
  - company ID
  - route/job name
  - integration event ID when applicable

### Suggested files

- `app/Http/Middleware/AttachRequestCorrelationId.php`
- `app/Support/Logging/*`

## 8.2 Queue Failure Dashboards And Dead-Letter Tooling

### Goal

Give operators visibility into failed jobs and webhook/report dead letters.

### Deliverables

- company webhook dead-letter UI
- platform queue health dashboard
- failed job summary cards
- replay / retry tooling
- grouping by job type, company, and failure reason

### Backend

Use:

- failed jobs table
- webhook delivery status
- report export failures
- invite/send failures where useful

### UI

Suggested pages:

- `resources/js/pages/platform/operations/queue-health.tsx`
- company webhook dead-letter views within integrations workspace

## 8.3 Backup And Recovery Runbook

### Goal

Document and validate operational recovery steps for PostgreSQL and attachments.

### Deliverables

- documented backup cadence
- restore test procedure
- attachment restore verification steps
- environment-specific commands for:
  - database dump
  - database restore
  - media backup

This is partly documentation and partly operational scripts.

Suggested outputs:

- `docs/backup-and-recovery.md`
- optional scripts under `scripts/ops/*`

## 8.4 Performance And Index Review

### Goal

Review the heaviest queries and add missing indexes before scale issues appear.

### Targets

- dashboard queries
- reports center filters
- approvals queue
- audit logs
- webhook deliveries
- recurring billing
- stock balances and stock moves

### Deliverables

- EXPLAIN-based query review
- index additions where needed
- eager-loading cleanup
- async/export offloading for heavy aggregates

### Output

- performance review doc
- migration for additional indexes

## 8.5 Alerting

### Goal

Make failures visible quickly.

### Alerts to add

- queue backlog spike
- failed jobs above threshold
- webhook dead-letter spike
- report export failures
- reconciliation/import failure spike
- repeated notification-delivery failure

Alerting can start simple with log/notification-based thresholds before full external monitoring integration.

## 8.6 Nightly Regression And Long-Running CI

### Goal

Add confidence beyond the standard PR test run.

### Deliverables

- nightly PostgreSQL full suite
- optional seeded-workflow regression run
- optional browser smoke flow for:
  - login
  - dashboard
  - sales order
  - invoice/payment
  - report export

### CI additions

- scheduled GitHub Actions workflow
- artifact retention for failed screenshots/logs if browser tests are added later

## 9) Cross-Track Standards

The following standards apply to every track in this document.

## 9.1 Authorization

- every new page and action must be policy-protected
- company scoping must be enforced at controller and query level
- platform-only operations must remain inaccessible from company users

## 9.2 Auditability

- create/update/delete/retry/rotate/post actions should be auditable
- especially for webhook secrets, cycle count postings, and replenishment triggers

## 9.3 API Consistency

- new API fields must respect the Phase 0 shared response contract
- new write endpoints should be evaluated for idempotency before release

## 9.4 Tests

Each track should include:

- feature tests
- permission tests
- integration-flow tests
- PostgreSQL verification in the full suite

## 10) Suggested Release Milestones

## Milestone 1: Integrations Operable

Includes:

- webhook company UI
- delivery analytics
- dead-letter visibility
- retry tooling

Exit criteria:

- company admins can run webhook integrations without raw API management

## Milestone 2: Inventory Depth Complete

Includes:

- lots/serials
- cycle counts
- reordering rules
- kits/bundles

Exit criteria:

- Port-101 inventory supports traceability, audit counts, replenishment, and bundled fulfillment

## Milestone 3: Integration Contract Hardened

Includes:

- idempotency
- external references
- webhook governance hardening
- API versioning policy

Exit criteria:

- third-party integration behavior is stable and supportable

## Milestone 4: Production Ready

Includes:

- observability
- queue failure dashboards
- dead-letter tooling
- backup/recovery runbook
- performance/index review
- alerting
- nightly CI

Exit criteria:

- the current Port-101 planned delivery can be operated with production discipline

## 11) What Comes After This Plan

Once this plan is complete, the current planned delivery is effectively done.

What remains after that is expansion, not completion:

- project templates / planning / customer collaboration
- inbound connectors
- light manufacturing
- POS
- eCommerce
- other vertical modules

Those should be planned as separate expansion tracks, not mixed into this closing plan.
