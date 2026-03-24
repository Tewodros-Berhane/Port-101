# API V1 Strategy And Implementation Plan

## Purpose

`/api/v1` exists to give Port-101 a stable integration contract.

It is not the transport layer for the current Laravel + Inertia web app. The current web UI is rendered through page responses and page props. That is appropriate for internal browser UX, but it is not a suitable long-term contract for external systems because page props change whenever the UI changes.

`API v1` exists for:

- external integrations
- mobile clients
- partner/customer portals later
- automation scripts
- service-to-service access
- future connectors and webhook consumers
- machine-readable access to stable business resources

In short:

- `Inertia` serves the internal web application
- `API v1` serves external and integration-facing workflows

## API Vs Inertia

This is the boundary Port-101 should keep:

- use `Inertia` for internal page composition, dashboards, workspace views, admin-heavy flows, and UX-specific aggregates
- use `API` for stable business resources and stable business actions

Examples:

- dashboard cards belong in `Inertia`
- a `sales order`, `project`, `invoice`, or `stock move` belongs in `API`
- a multi-step bank reconciliation review screen belongs in `Inertia`
- a stable action like `approve order` or `submit timesheet` can belong in `API`

This distinction matters because:

- the web UI can evolve quickly
- the API should evolve slowly
- external clients need predictable contracts
- internal pages need flexible payloads

## Current API V1 Scope

`API v1` is already present, but it is still partial.

Current routes in `routes/api.php`:

```text
GET    /api/v1/health

GET    /api/v1/partners
POST   /api/v1/partners
GET    /api/v1/partners/{partner}
PUT    /api/v1/partners/{partner}
PATCH  /api/v1/partners/{partner}
DELETE /api/v1/partners/{partner}

GET    /api/v1/products
POST   /api/v1/products
GET    /api/v1/products/{product}
PUT    /api/v1/products/{product}
PATCH  /api/v1/products/{product}
DELETE /api/v1/products/{product}

GET    /api/v1/projects
POST   /api/v1/projects
GET    /api/v1/projects/{project}
PUT    /api/v1/projects/{project}
PATCH  /api/v1/projects/{project}
DELETE /api/v1/projects/{project}

GET    /api/v1/projects/{project}/tasks
POST   /api/v1/projects/{project}/tasks
GET    /api/v1/projects/tasks/{task}
PUT    /api/v1/projects/tasks/{task}
PATCH  /api/v1/projects/tasks/{task}
DELETE /api/v1/projects/tasks/{task}

GET    /api/v1/projects/{project}/timesheets
POST   /api/v1/projects/{project}/timesheets
GET    /api/v1/projects/timesheets/{timesheet}
PUT    /api/v1/projects/timesheets/{timesheet}
PATCH  /api/v1/projects/timesheets/{timesheet}
DELETE /api/v1/projects/timesheets/{timesheet}
POST   /api/v1/projects/timesheets/{timesheet}/submit
POST   /api/v1/projects/timesheets/{timesheet}/approve
POST   /api/v1/projects/timesheets/{timesheet}/reject

GET    /api/v1/settings
PUT    /api/v1/settings
```

Current characteristics:

- the API is protected by `auth:sanctum`
- company scoping uses `company.context` and `company` middleware
- current coverage is focused on master data plus Projects
- the rest of the ERP still runs mainly through web routes

This means the API is currently an early integration surface, not a full ERP contract.

## Why The API Exists Even Though Inertia Already Pushes Data

This is the key architectural point:

- Inertia props are a UI transport
- the API is a stable external contract

If Port-101 only existed as an internal browser app, the API could stay minimal.

But the product is being built as an ERP. That implies eventual needs like:

- mobile time entry
- external order ingestion
- marketplace or partner integrations
- customer or vendor portals
- BI or reporting consumers
- background jobs and connectors outside the browser session

Those clients should not depend on internal page payloads.

## Why Projects Was Exposed Early

Projects was exposed before many other modules because it has clear integration value:

- mobile timesheet entry
- service-delivery tooling
- project provisioning after sales confirmation
- billing handoff support
- external service teams or field workflows later

That does not mean Projects is the only module suitable for API.

It means Projects had:

- a clear external use case
- a stable enough workflow surface
- an explicit implementation request

The remaining modules should be exposed in a deliberate order, not mirrored all at once.

## API V1 Design Principles

### 1. Stable Contracts

Once an endpoint is public, its request and response shapes should remain stable across normal product iteration.

UI payloads can change quickly.
API contracts should not.

### 2. Resource-First Design

Expose real business entities and business actions:

- leads
- quotes
- orders
- projects
- tasks
- timesheets
- invoices
- payments
- stock moves

Do not expose dashboard cards or page-only aggregates as if they were system resources.

### 3. Company Scoping Is Mandatory

Every protected API endpoint must remain scoped to the authenticated user's active company context.

That must apply to:

- list
- show
- create
- update
- delete
- workflow actions

No cross-company leakage is acceptable.

### 4. API Must Reuse Existing Domain Services

API controllers should not introduce parallel business logic.

They should reuse the same services already used by the web app, for example:

- sales workflow services
- project workflow services
- accounting workflow services
- approval services

This keeps behavior aligned across:

- web
- API
- jobs
- future automation entry points

### 5. Actions Must Be Explicit

ERP workflows are not pure CRUD.

Actions such as these should remain explicit:

- `POST /quotes/{quote}/confirm`
- `POST /orders/{order}/approve`
- `POST /projects/timesheets/{timesheet}/submit`
- `POST /invoices/{invoice}/post`

This is better than hiding workflow transitions inside generic updates.

### 6. Do Not Freeze Unstable Internal UX Too Early

Some flows are still better kept behind internal web screens until there is a real external consumer.

Examples:

- bank statement rematch review
- dashboard personalization
- admin governance analytics
- notification dropdown payloads
- highly interactive report builders

### 7. Prefer Narrow, Useful Contracts

`API v1` should expose integration-worthy resources first.

It should not attempt to mirror every web page.

The safer approach is:

- expose stable workflows first
- keep unstable UX in the web layer
- expand only where external consumption is realistic

## What Belongs In API V1

These categories are correct API candidates:

### Business Resources

- partners
- products
- leads
- quotes
- orders
- projects
- tasks
- timesheets
- purchase orders
- invoices
- payments
- stock moves

### Business Actions

- send quote
- confirm quote
- approve order
- confirm order
- submit timesheet
- approve timesheet
- reject timesheet
- post invoice
- reverse payment

### Integration-Oriented Reads

- stock availability
- invoice status
- payment status
- approval queue summaries
- export job status

### Async Integration Jobs

- export generation
- webhook delivery history later
- import jobs later

## What Should Stay Inertia-Only

These areas should remain primarily web-only for now.

### Platform And Superadmin UX

- platform dashboard
- governance pages
- admin user management UI
- operations reporting page composition

### Dashboard Composition

- company dashboard cards
- module landing pages
- chart payloads built just for UI
- widget aggregates

### UX-Only Page Payloads

- filter dropdown option payloads for single pages
- breadcrumbs and layout composition data
- workspace-only summaries built for specific React screens

### Operational Review Wizards

- bank statement review and rematch UI
- attachment-heavy manual journal edit/review flows
- report builder screens
- personalization and appearance screens

### Raw Internal Admin Surfaces

- audit log browsing UI payloads
- notification bell dropdown behavior
- platform governance analytics cards

### High-Risk Finance Internals

- direct ledger mutation
- reconciliation internals
- reversal-heavy accounting operations without tighter external constraints

## Expose Later Only If There Is A Real Client

These are valid candidates later, but should not be treated as early API priorities unless an actual consumer needs them:

- project activity feed
- notification inbox payloads
- recurring billing schedule management
- dashboard KPI aggregates
- raw audit log slices

The rule should be:

- real client first
- contract second
- no speculative API surface

## API Contract Standards

Before large API expansion, Port-101 should standardize the contract.

### Authentication

Use Sanctum tokens for integration clients.

Current expectation:

- protected routes use `auth:sanctum`
- integrations use bearer tokens

Planned hardening:

- token naming conventions
- token scopes/abilities by module
- integration owner tracking

### Authorization

The API must use the same policies and permissions as the web app.

No separate, weaker API rule set should exist.

### Company Context

All endpoints must honor:

- active company resolution
- company membership restrictions
- company role and policy checks

No endpoint should return data outside the active company context.

### Response Shape

Resource lists should stay predictable.

Recommended list shape:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

Recommended resource shape:

```json
{
  "data": {
    "id": 1,
    "name": "Example",
    "created_at": "2026-03-22T10:00:00Z",
    "updated_at": "2026-03-22T10:00:00Z"
  }
}
```

### Filtering

Keep filtering query-string based.

Common filters should stay consistent across modules:

- `search`
- `status`
- `approval_status`
- `date_from`
- `date_to`
- `sort`
- `direction`

Then add module-specific filters only where justified.

### Error Shape

Minimum behavior:

- validation errors return `422`
- unauthorized returns `401`
- forbidden returns `403`
- missing resources return `404`
- invalid workflow state returns `422`

Planned standard error envelope:

```json
{
  "message": "Validation failed.",
  "errors": {
    "field": [
      "The field is required."
    ]
  }
}
```

### Idempotency

This becomes important as more write-heavy endpoints are exposed.

Recommended support before broader rollout:

- `external_reference` on business resources where sync is common
- optional `idempotency_key` header or request field for create/post actions

Priority candidates:

- sales quote creation
- sales order creation
- invoice creation
- payment creation

### External References

ERP resources that may sync to outside systems should eventually support an external reference.

Priority candidates:

- partner
- product
- lead
- quote
- order
- project
- invoice
- payment

### Timestamps And Auditability

All API resources should return ISO 8601 timestamps.

Important workflow actions should also remain traceable through:

- actor fields
- status timestamps
- audit logs already present in the application

### Versioning

Keep the `/api/v1` prefix.

Implemented rules:

- breaking changes require a new version
- non-breaking additions are allowed in `v1`
- every `/api/v1` response emits `X-API-Version: v1`
- when deprecation is scheduled, responses also emit:
  - `Deprecation`
  - `Sunset`

Current lifecycle contract:

- `v1` is the current stable public contract
- deprecation dates are controlled centrally through the API versioning config
- error responses and file downloads follow the same version-header policy as normal JSON resources

Changelog policy:

- every API-affecting change should be classified internally as one of:
  - `breaking`
  - `additive`
  - `behavioral`
  - `bugfix`
  - `operational`
- only `breaking` changes require a new version line
- `additive`, `behavioral`, `bugfix`, and `operational` changes may ship inside `v1` as long as they do not invalidate existing consumers

### Pagination And Performance

Standardize:

- default page size
- max page size
- eager loading rules
- searchable/sortable fields per endpoint

This becomes important before Sales and Inventory are exposed more broadly.

## Implementation Standards

The implementation pattern for each API module should stay consistent.

For each module, prefer this structure:

### Routes

Add grouped routes under `routes/api.php` using `/api/v1/{module}` prefixes where appropriate.

### Controllers

Place controllers under `app/Http/Controllers/Api/V1`.

Examples:

- `SalesLeadsController`
- `SalesQuotesController`
- `SalesOrdersController`

### Requests

Use dedicated API requests under `app/Http/Requests/Api/V1/...` if validation starts diverging from web request rules.

If the web request rules are already stable and reusable, share them.

### Resources

Use API resources or a consistent JSON mapping layer so responses do not drift controller by controller.

Recommended direction:

- `app/Http/Resources/Api/V1/...`

### Services

Controllers should call existing services instead of owning workflow logic.

Examples:

- quote confirm uses the sales workflow service
- invoice posting uses the accounting workflow service
- timesheet approval uses the project workflow service

### Policies

API endpoints should call:

- `authorizeResource`
- explicit `authorize(...)`
- existing policies for resource and action control

### Tests

Each module rollout should include:

- token auth coverage
- company scoping coverage
- permission coverage
- workflow action coverage
- happy-path CRUD coverage
- validation failure coverage

## Correct API Expansion Order For Port-101

This is the right rollout order.

### Phase 0: Contract Hardening

Do this before major API growth.

Deliverables:

- standard response envelope rules
- standard error envelope rules
- common filtering rules
- token usage conventions
- idempotency strategy
- API resource mapping approach
- versioning and deprecation note

This is the layer that prevents the API from growing inconsistently.

### Phase 1: Sales API

This should be the next implementation target.

Expose:

- leads
- quotes
- quote lines
- orders
- order lines

Actions:

- send quote
- confirm quote
- approve order
- confirm order

Why first:

- Sales is the main external entry point into the ERP
- it feeds Projects, Inventory, and Accounting
- it is the highest-value missing integration surface

Recommended route set:

```text
GET    /api/v1/sales/leads
POST   /api/v1/sales/leads
GET    /api/v1/sales/leads/{lead}
PUT    /api/v1/sales/leads/{lead}
PATCH  /api/v1/sales/leads/{lead}
DELETE /api/v1/sales/leads/{lead}

GET    /api/v1/sales/quotes
POST   /api/v1/sales/quotes
GET    /api/v1/sales/quotes/{quote}
PUT    /api/v1/sales/quotes/{quote}
PATCH  /api/v1/sales/quotes/{quote}
DELETE /api/v1/sales/quotes/{quote}
POST   /api/v1/sales/quotes/{quote}/send
POST   /api/v1/sales/quotes/{quote}/confirm

GET    /api/v1/sales/orders
POST   /api/v1/sales/orders
GET    /api/v1/sales/orders/{order}
PUT    /api/v1/sales/orders/{order}
PATCH  /api/v1/sales/orders/{order}
DELETE /api/v1/sales/orders/{order}
POST   /api/v1/sales/orders/{order}/approve
POST   /api/v1/sales/orders/{order}/confirm
```

Implementation checklist:

- controllers under `Api/V1`
- request validation
- resource transformers
- policy enforcement
- service reuse
- feature tests for company scoping and workflow actions

### Phase 2: Inventory API

Expose Inventory after Sales.

First wave:

- stock balances
- stock moves
- fulfillment visibility

Second wave:

- reserve
- dispatch
- receive
- complete move

Why second:

- external systems often need stock truth immediately after orders
- availability and fulfillment are the next integration concern after sales intake

Recommended route set:

```text
GET  /api/v1/inventory/stock-balances
GET  /api/v1/inventory/stock-moves
GET  /api/v1/inventory/stock-moves/{move}
POST /api/v1/inventory/stock-moves/{move}/reserve
POST /api/v1/inventory/stock-moves/{move}/dispatch
POST /api/v1/inventory/stock-moves/{move}/receive
POST /api/v1/inventory/stock-moves/{move}/complete
```

### Phase 3: Purchasing API

Expose Purchasing after Inventory.

Expose:

- RFQs
- purchase orders
- receipts

Actions:

- approve
- confirm
- receive

Why third:

- procurement integrations usually follow stock visibility
- supplier-facing automation becomes valuable once inventory data is already exposed

Recommended route set:

```text
GET    /api/v1/purchasing/rfqs
POST   /api/v1/purchasing/rfqs
GET    /api/v1/purchasing/rfqs/{rfq}
PUT    /api/v1/purchasing/rfqs/{rfq}
PATCH  /api/v1/purchasing/rfqs/{rfq}
POST   /api/v1/purchasing/rfqs/{rfq}/approve

GET    /api/v1/purchasing/orders
POST   /api/v1/purchasing/orders
GET    /api/v1/purchasing/orders/{order}
PUT    /api/v1/purchasing/orders/{order}
PATCH  /api/v1/purchasing/orders/{order}
POST   /api/v1/purchasing/orders/{order}/confirm
POST   /api/v1/purchasing/orders/{order}/receive
```

### Phase 4: Accounting API

Expose Accounting in a controlled scope.

Expose first:

- customer invoices
- vendor bills
- invoice status
- payments

Actions:

- post invoice
- cancel invoice
- post payment
- reverse payment

Do not expose early:

- direct ledger operations
- manual journals
- bank reconciliation internals
- unreconcile/rematch internals

Why:

- invoices and payments are valid integration contracts
- ledger and reconciliation internals are higher-risk finance operations

Recommended route set:

```text
GET    /api/v1/accounting/invoices
POST   /api/v1/accounting/invoices
GET    /api/v1/accounting/invoices/{invoice}
PUT    /api/v1/accounting/invoices/{invoice}
PATCH  /api/v1/accounting/invoices/{invoice}
POST   /api/v1/accounting/invoices/{invoice}/post
POST   /api/v1/accounting/invoices/{invoice}/cancel

GET    /api/v1/accounting/payments
POST   /api/v1/accounting/payments
GET    /api/v1/accounting/payments/{payment}
POST   /api/v1/accounting/payments/{payment}/post
POST   /api/v1/accounting/payments/{payment}/reverse
```

### Phase 5: Approvals API

Expose after the underlying document APIs are in place.

Expose:

- approval request listing
- approval request detail
- approve
- reject

Why:

- lightweight approval clients are useful
- but approvals are secondary to the documents they govern

Recommended route set:

```text
GET  /api/v1/approvals/requests
GET  /api/v1/approvals/requests/{request}
POST /api/v1/approvals/requests/{request}/approve
POST /api/v1/approvals/requests/{request}/reject
```

### Phase 6: Reports And Exports API

Do not mirror dashboard payloads.

Expose export jobs instead:

- create export
- poll export status
- download export file

Why:

- external systems usually want a file or extract
- not the page-level data structure used by the web app

Recommended route set:

```text
POST /api/v1/reports/exports
GET  /api/v1/reports/exports/{export}
GET  /api/v1/reports/exports/{export}/download
```

### Phase 7: Webhooks And Outbound Integration Events

After the main APIs are stable, add outbound events.

Recommended first events:

- sales order confirmed
- project provisioned
- invoice posted
- payment received
- stock delivered

This should come after stable resource APIs, not before.

## Recommended Next Implementation Target

If Port-101 continues API work next, the correct next implementation target is `Phase 7: Webhooks And Outbound Integration Events`.

Reason:

- Phases 0 through 6 are now covered, so the remaining integration gap is outbound delivery.
- External systems can already create, read, and mutate records through `API v1`.
- What is still missing is real-time propagation from Port-101 to those systems when business state changes.
- Without outbound events, integrators still have to poll the API for every important workflow change.

Without Phase 7, the integration story is still incomplete.

## Concrete Phase 7 Implementation Plan

This is the next practical execution plan if implementation starts immediately.

### Scope And Boundaries

This phase is about outbound notifications from Port-101 to external systems.

It is not:

- internal notification-center UX
- email delivery
- dashboard polling payloads
- inbound webhook processing
- another copy of existing business logic

Phase 7 should be company-scoped first.

Reason:

- webhook destinations represent customer integrations
- event subscriptions differ by company
- signing secrets and delivery history should belong to the company that owns the integration

Platform-level outbound events can be added later if there is a real operational need.

### Delivery Model

Port-101 should use an outbox-style flow, not direct inline webhook calls from controllers.

Recommended flow:

1. A business workflow completes.
2. The workflow records an internal integration event.
3. A queued fan-out job finds subscribed webhook endpoints.
4. One delivery record is created per endpoint.
5. A delivery job signs and sends the payload.
6. Delivery success or failure is persisted for retries and auditability.

This gives Port-101:

- at-least-once delivery semantics
- retry support
- delivery history
- decoupling from request latency
- less risk of business actions failing because a third-party endpoint is slow

### Data Model

The initial schema should include three tables.

#### `webhook_endpoints`

Purpose:

- company-owned subscription targets

Suggested fields:

- `id`
- `company_id`
- `name`
- `target_url`
- `signing_secret`
- `api_version`
- `is_active`
- `subscribed_events` as JSON array
- `last_tested_at`
- `created_by`
- `updated_by`
- timestamps

#### `integration_events`

Purpose:

- durable outbound event outbox

Suggested fields:

- `id`
- `company_id`
- `event_type`
- `aggregate_type`
- `aggregate_id`
- `occurred_at`
- `payload` as JSON
- `published_at`
- `created_by`
- timestamps

#### `webhook_deliveries`

Purpose:

- actual delivery attempts per endpoint and event

Suggested fields:

- `id`
- `company_id`
- `webhook_endpoint_id`
- `integration_event_id`
- `event_type`
- `status`
- `attempt_count`
- `last_attempt_at`
- `next_retry_at`
- `response_status`
- `response_body_excerpt`
- `failure_message`
- `delivered_at`
- timestamps

Recommended status values:

- `pending`
- `processing`
- `delivered`
- `failed`
- `dead`

### Laravel Structure

Recommended implementation pieces:

#### Models

- `App\Modules\Integrations\Models\WebhookEndpoint`
- `App\Modules\Integrations\Models\IntegrationEvent`
- `App\Modules\Integrations\Models\WebhookDelivery`

#### Services

- `WebhookEndpointService`
- `OutboundEventService`
- `WebhookDeliveryService`
- `WebhookSignatureService`

#### Jobs

- `FanOutIntegrationEvent`
- `DeliverWebhook`

#### Policies

- `WebhookEndpointPolicy`
- `WebhookDeliveryPolicy`

#### Requests

- `WebhookEndpointStoreRequest`
- `WebhookEndpointUpdateRequest`
- `WebhookDeliveryRetryRequest`

#### API Controllers

- `Api\\V1\\WebhookEndpointsController`
- `Api\\V1\\WebhookDeliveriesController`

#### Web Controllers

- company settings/integrations UI controller for webhook administration

### API V1 Management Routes

This phase should add management endpoints to `API v1`.

Recommended route set:

```text
GET    /api/v1/webhooks/endpoints
POST   /api/v1/webhooks/endpoints
GET    /api/v1/webhooks/endpoints/{endpoint}
PATCH  /api/v1/webhooks/endpoints/{endpoint}
DELETE /api/v1/webhooks/endpoints/{endpoint}

POST   /api/v1/webhooks/endpoints/{endpoint}/rotate-secret
POST   /api/v1/webhooks/endpoints/{endpoint}/test

GET    /api/v1/webhooks/endpoints/{endpoint}/deliveries
GET    /api/v1/webhooks/deliveries/{delivery}
POST   /api/v1/webhooks/deliveries/{delivery}/retry
```

These routes are for managing Port-101 outbound integrations.

They are not the routes that external systems receive.

### Event Contract

Every emitted event should have a stable outer envelope.

Recommended payload shape:

```json
{
  "event_id": "uuid",
  "event_type": "sales.order.confirmed",
  "api_version": "v1",
  "occurred_at": "2026-03-23T10:15:00Z",
  "company_id": "uuid",
  "data": {
    "object_type": "sales_order",
    "object_id": "uuid",
    "reference": "SO-10021",
    "status": "confirmed"
  }
}
```

Rules:

- `event_id` must be globally unique
- `event_type` must be stable and namespaced
- `occurred_at` must reflect the business event time, not the delivery time
- `data` must be a compact business snapshot, not a full page payload
- consumers must be told to deduplicate by `event_id`

### Signature And Security Rules

Every webhook request should be signed.

Recommended headers:

- `X-Port101-Event`
- `X-Port101-Event-Id`
- `X-Port101-Timestamp`
- `X-Port101-Signature`

Recommended signature rule:

- HMAC SHA-256 over `timestamp + "." + raw_body`
- using the endpoint's signing secret

Recommended additional constraints:

- require HTTPS in production
- allow `http://localhost` only for local development/testing
- set request timeout aggressively, for example 10 seconds
- treat only `2xx` as success
- store only a truncated response body excerpt
- never include secrets or internal admin metadata in event payloads

### Delivery And Retry Rules

Delivery should be asynchronous and at-least-once.

Recommended retry policy:

- first attempt immediately
- retry 1 after 1 minute
- retry 2 after 5 minutes
- retry 3 after 15 minutes
- retry 4 after 1 hour
- move to `dead` after final failure

Recommended delivery behavior:

- one failed endpoint must not block other endpoints
- deliveries must be idempotent from the consumer perspective
- retries must reuse the same `event_id`
- manual retry should create a new attempt on the same delivery record

### Initial Event Set

Phase 7 should start with events that are already backed by stable workflows in the codebase.

Recommended first events:

#### Sales

- `sales.order.confirmed`

Trigger point:

- `SalesOrderWorkflowService::confirm()`

#### Projects

- `projects.project.provisioned`

Trigger point:

- the service-order to project provisioning flow that runs after confirmed sales orders

#### Accounting

- `accounting.invoice.posted`
- `accounting.payment.received`

Trigger points:

- `AccountingInvoiceWorkflowService::post()`
- payment event should be emitted when payment has actually been applied to business state
- in the current workflow model, that likely means the reconcile path, not only payment draft creation

#### Inventory

- `inventory.delivery.completed`

Trigger point:

- delivery completion in the stock move workflow

### UI And Operational Surface

Port-101 should also expose a company-side management UI.

Recommended location:

- `Settings -> Integrations -> Webhooks`

Recommended UI capabilities:

- list endpoints
- create/edit endpoint
- enable/disable endpoint
- choose subscribed events
- rotate secret
- send test event
- inspect recent deliveries
- inspect failure reason and response code
- retry a failed delivery

### Testing Plan

Minimum test coverage should include:

#### Endpoint Management

- company-scoped CRUD
- permission enforcement
- secret rotation
- event subscription validation

#### Event Publishing

- business workflow creates an integration event
- correct event type is published
- payload contains expected identifiers and references

#### Delivery

- successful `2xx` marks delivery as delivered
- non-`2xx` marks delivery failed and schedules retry
- timeout/network failure schedules retry
- final exhaustion marks delivery dead

#### Security

- signature header is present
- signature is computed from the correct raw payload
- disabled endpoints do not receive deliveries

#### Isolation

- one company cannot view or retry another company's deliveries
- one failing endpoint does not block other endpoints subscribed to the same event

### Step-By-Step Execution Order

#### Step 1: Schema And Policies

Build:

- `webhook_endpoints`
- `integration_events`
- `webhook_deliveries`
- policies for endpoint and delivery access

#### Step 2: Endpoint Management Layer

Build:

- endpoint CRUD
- event subscription editing
- secret rotation
- test-send action

Deliver via:

- company web UI
- `API v1` management endpoints

#### Step 3: Outbound Event Service

Build:

- shared `OutboundEventService`
- helper for recording events from domain workflows

Important:

- business workflows should write events, not send HTTP directly

#### Step 4: Delivery Worker

Build:

- queued fan-out job
- queued delivery job
- signature generation
- retry scheduling
- delivery persistence

#### Step 5: Wire First Business Events

Wire:

- sales order confirmed
- project provisioned
- invoice posted
- payment received
- delivery completed

#### Step 6: Observability And Operations

Add:

- delivery history screens
- last success/failure timestamps
- retry action
- dead-letter visibility

#### Step 7: Documentation And Consumer Guidance

Document:

- event types
- payload examples
- signature verification example
- retry semantics
- idempotency guidance

### Recommendation

When implementation starts, build Phase 7 in this order:

1. webhook endpoint schema and management
2. integration event outbox
3. delivery jobs and signing
4. first event sources from Sales, Projects, Accounting, and Inventory
5. delivery history and retry tooling

## Non-Goals For API V1

`API v1` should not attempt to:

- mirror every Inertia page
- expose every admin dashboard payload
- expose unstable wizard internals
- expose raw finance internals without clear constraints
- create duplicate business logic paths separate from the web app

## Practical Summary

Port-101 should treat `API v1` as a selective integration layer.

It should expose:

- stable business resources
- stable business actions
- workflows with real external value

It should keep `Inertia` for:

- dashboards
- page composition
- operational review screens
- platform administration UX
- unstable internal workflows

That is the correct boundary for a production-ready ERP that needs both:

- a strong internal web app
- a stable external integration surface
