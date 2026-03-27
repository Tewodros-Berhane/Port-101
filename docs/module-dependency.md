# Module Dependency Map

## Purpose

Define how modules depend on each other, which tables/services are shared, and how major module implementations fit into Port-101.

This file now serves two purposes:

- the dependency reference for the current ERP architecture
- the execution blueprint for major module plans such as `Projects/Services` and `HR / People Ops`

## Architecture Baseline

Port-101 currently follows this structure:

- Shared platform and cross-cutting concerns under `app/Core`
- Independent business modules under `app/Modules`
- Module web routes under `routes/moduleroutes/*.php`
- Inertia/React module pages under `resources/js/pages/*`
- Company workspace routes under `/company/*`
- Platform routes under `/platform/*`
- API routes under `/api/v1/*`

Cross-cutting services already available to all modules:

- auth and RBAC
- company scoping and active-company middleware
- settings persistence
- audit logging
- attachments/media
- in-app notifications
- approval authority and approval queue integration
- PDF/XLSX reporting patterns
- Sanctum API authentication

## Dependency Graph (High-Level)

- **Core** -> required by all modules
- **Sales** -> depends on Core
- **Inventory** -> depends on Core + Sales
- **Purchasing** -> depends on Core + Inventory
- **Accounting** -> depends on Core + Sales + Purchasing
- **Projects/Services** -> depends on Core + Accounting, and integrates with Sales, Approvals, Reports, Notifications, and Attachments
- **HR / People Ops** -> depends on Core + Accounting, and integrates with Projects, Approvals, Reports, Notifications, and Attachments
- **Reporting** -> depends on all modules

## Current Module Dependencies

### Core Platform

All modules depend on Core.

Core provides:

- `users`, `roles`, `permissions`
- `companies`, `company_users`
- master data: `partners`, `products`, `taxes`, `currencies`, `uoms`, `price_lists`
- `settings`
- audit, notifications, attachments, and approval services

### Sales Module

Depends on Core tables/services:

- partners, products, taxes, currencies, users, companies
- approval authority and approval queue
- numbering sequences and settings defaults

Provides data to:

- Inventory: sales order reservation/delivery pipeline
- Accounting: invoice draft generation and readiness signals
- Projects/Services: optional project creation from sold service orders

### Inventory Module

Depends on:

- Core: products, uoms, companies, users
- Sales: sales orders and sales order lines for reservations

Provides data to:

- Purchasing: receipt/stock visibility
- Accounting: delivery confirmation and stock-linked financial timing
- Projects/Services: optional material consumption against project work in later phases

### Purchasing Module

Depends on:

- Core: vendors, products, taxes, currencies, users
- Inventory: receipts and stock movement context
- Approvals: PO approval flow

Provides data to:

- Accounting: vendor bill handoff
- Projects/Services: optional rebillable vendor costs in later phases

### Accounting Module

Depends on:

- Core: partners, taxes, currencies, users, settings
- Sales: AR invoice generation from orders
- Purchasing: AP bills from receipts/orders

Provides data to:

- Projects/Services: customer invoice draft generation for time, milestone, and rebillable service work
- Reports: P&L, balance sheet, trial balance, cash flow, aging, ledger views

### HR / People Ops Module

Depends on:

- Core: companies, users, roles, settings, attachments, notifications, audit, approval services
- Accounting: reimbursement payout handoff, payroll accrual posting, payable settlement

Integrates tightly with:

- Reports: headcount, leave balances, attendance anomalies, payroll register, reimbursement aging
- Approvals: leave approval, attendance correction approval, reimbursement approval, payroll release
- Projects/Services: optional labor-cost allocation and later capacity/timesheet integration

Provides data to:

- Accounting: reimbursement and payroll posting payloads
- Reports: HR and workforce reporting datasets
- Projects/Services: optional employee cost and availability context

Implementation sequence against the rest of the ERP:

1. Employee foundation can start immediately because Core dependencies already exist.
2. Leave and Attendance stay mostly inside HR + Core + Approvals.
3. Reimbursements become useful once Accounting handoff is wired.
4. Payroll Lite should only start after employee contracts, leave, and attendance are stable.
5. Projects/Services integration should stay optional until HR records and payroll costing are trustworthy.

Detailed implementation blueprint:

- `docs/hr-module-plan.md`

### Reports Module

Depends on:

- Core + every business module

Provides:

- operational exports
- financial exports
- scheduled report delivery
- PDF/XLSX rendering patterns reusable by future modules

## Cross-Module Event Rules

Port-101 should continue following this rule:

- Modules should not directly mutate each other's tables except through explicit workflow services.
- Cross-module coordination should happen through domain services and explicit events.

Current and planned event examples:

- `SalesOrderConfirmed` -> reserve inventory
- `SalesOrderConfirmed` -> create AR invoice draft when policy requires
- `StockDelivered` -> mark invoice ready
- `PurchaseReceiptCompleted` -> create vendor bill draft
- `ProjectBillableApproved` -> create customer invoice draft
- `ProjectMilestoneCompleted` -> mark milestone billable
- `ProjectTimesheetApproved` -> update billable quantity/cost snapshot
- `LeaveRequestApproved` -> create payroll work-entry impact
- `AttendanceRecordFinalized` -> refresh payroll work entries
- `ReimbursementClaimApproved` -> create Accounting reimbursement payout or payslip reimbursement handoff
- `PayrollRunPosted` -> create Accounting accrual linkage

## HR / People Ops Dependency Matrix

This section defines exactly how the planned HR module should sit against the existing modules.

| Module | Dependency level | HR consumes | HR publishes back |
| --- | --- | --- | --- |
| Core | Hard | companies, users, roles, settings, attachments, notifications, audit, approvals | employee-scoped attachments, audit records, HR notification traffic |
| Accounting | Hard | reimbursement payout flow, payroll accrual posting, payable settlement context | reimbursement payment payloads, payroll accrual entries, payment/settlement linkage |
| Reports | Strong | export infrastructure, report job pipeline, PDF/XLSX patterns | headcount, leave, attendance, reimbursement, payroll reporting datasets |
| Approvals | Strong | approval authority and threshold rules | leave, attendance correction, reimbursement, payroll release approval events |
| Projects/Services | Optional strong | project references for rebillable claims or labor-cost allocation | employee availability context, approved leave visibility, optional labor cost allocation |
| Notifications | Strong | in-app and email delivery mechanisms | employee/manager approval and status notifications |
| Attachments | Strong | employee documents, receipts, payslip and contract file storage | attachment metadata under HR retention/privacy rules |
| Sales | Weak/optional | none for MVP | none initially, later possible commission context |
| Inventory | Weak/optional | none for MVP | none initially |
| Purchasing | Weak/optional | none for MVP | none initially |

HR implementation guardrails:

- HR should not be blocked on Sales, Inventory, or Purchasing.
- HR should integrate with Accounting only through workflow services and explicit posting/reimbursement handoff.
- HR should integrate with Projects only after core employee, leave, attendance, and payroll data becomes reliable enough to use for capacity or cost allocation.

## HR / People Ops Delivery Order

If HR is the next major module after the current ERP scope, the correct dependency-aware rollout is:

1. Employees foundation
   - departments, designations, employees, contracts, documents, HR roles/policies
2. Leave
   - leave types, periods, allocations, requests, approvals
3. Attendance
   - shifts, check-ins, daily attendance records, correction requests
4. Reimbursements
   - claims, receipts, manager/finance approval, Accounting payout handoff
5. Payroll Lite
   - salary structures, compensation assignments, work entries, payroll runs, payslips, Accounting accrual posting
6. Reports + selective API/self-service completion

This order is intentional:

- leave and attendance generate the clean operational inputs payroll needs later
- reimbursements are useful early but do not require full payroll completion
- payroll should be built last because it has the tightest dependency on stable HR inputs and Accounting discipline

---

# Projects/Services Module Plan

## 1) What This Module Is

`Projects/Services` will be a first-class business module like Sales, Inventory, Purchasing, and Accounting.

Its job is to manage service delivery after a project or service engagement exists.

It should cover:

- project setup and delivery tracking
- tasks and work execution
- timesheets
- milestone-based delivery
- billable work review
- customer invoice handoff into Accounting
- project profitability and utilization reporting

## 2) Odoo-Inspired Positioning

Odoo does not treat all service operations as one single screen. It splits them across tightly integrated apps such as:

- Project
- Timesheets
- Planning
- Field Service
- Sales / Invoicing for billable service handoff

For Port-101, the right implementation is:

- one top-level `Projects/Services` module now
- internal subdomains inside that module for `projects`, `tasks`, `timesheets`, `milestones`, and `billing`
- optional later expansion into `Planning` and `Field Service` once the project core is stable

This keeps the architecture modular without fragmenting the product too early.

## 3) Business Goals

This module should allow a company to:

- run client projects end-to-end
- assign internal staff to projects/tasks
- track planned versus actual effort
- log billable and non-billable time
- bill customers by milestone or by time and materials
- see delivery health, backlog, utilization, and margin

## 4) Dependency Position in Port-101

### Hard dependencies

`Projects/Services` depends on:

- **Core**
  - company scoping
  - users/roles/permissions
  - partners/customers
  - products/service items
  - settings/numbering
  - attachments/notifications/audit
- **Accounting**
  - draft invoice creation
  - customer billing state tracking
  - payment visibility for project finance status

### Strong integration dependencies

`Projects/Services` should integrate tightly with:

- **Sales**
  - optional project creation from confirmed sales orders containing service products
  - commercial context such as customer, sold hours, sold milestones, contract amount
- **Approvals**
  - billable overrides
  - milestone acceptance exceptions
  - write-offs / invoice adjustments above threshold
- **Reports**
  - project margin, utilization, WIP, overdue milestones, invoice readiness

### Optional later dependencies

These should be phase-two or phase-three integrations, not MVP blockers:

- **Purchasing**
  - vendor/subcontractor costs linked to projects
  - rebillable costs
- **Inventory**
  - material consumption on project work
  - spare parts / service kits
- **Field Service / Planning equivalent**
  - technician dispatch
  - route planning
  - worksheets and on-site checklists

## 5) Module Scope Decision

### In scope for Phase 1

- projects
- project members
- project stages/status
- tasks
- timesheets
- milestones
- billable item queue
- invoice draft handoff to Accounting
- project dashboard and profitability summary

### Not in initial scope

- full resource planning calendar
- route optimization / technician dispatch
- complex dependency graphs / Gantt critical path engine
- subcontractor procurement automation
- expense reimbursements as a separate submodule
- customer portal project collaboration

## 6) Suggested Permission Model

Use a dedicated `projects.*` namespace.

Recommended permissions:

- `projects.projects.view`
- `projects.projects.manage`
- `projects.tasks.view`
- `projects.tasks.manage`
- `projects.tasks.assign`
- `projects.timesheets.view`
- `projects.timesheets.manage_own`
- `projects.timesheets.manage_team`
- `projects.timesheets.approve`
- `projects.milestones.view`
- `projects.milestones.manage`
- `projects.billables.view`
- `projects.billables.manage`
- `projects.billables.approve`
- `projects.invoices.create`
- `projects.profitability.view`
- `projects.templates.manage` (later)

Suggested functional roles:

- `Project Manager`
- `Project User` or `Consultant`
- `Service Manager` (optional naming if companies are more service-focused)

Role interaction with existing roles:

- Finance Manager / Accountant handle invoice posting after project billing handoff
- Approver can approve high-value billable exceptions
- Owner remains full-access fallback

## 7) Data Model and Migrations

Create these migrations under a new `Projects` module rollout.

### 7.1 `projects`

Purpose: root project record.

Suggested columns:

- `id` UUID
- `company_id`
- `customer_id` -> `partners.id`
- `sales_order_id` nullable -> `sales_orders.id`
- `project_code`
- `name`
- `description` nullable
- `status` (`draft`, `active`, `on_hold`, `completed`, `cancelled`)
- `billing_type` (`fixed_fee`, `time_and_material`, `non_billable`, `mixed`)
- `currency_id`
- `project_manager_id` nullable -> `users.id`
- `start_date` nullable
- `target_end_date` nullable
- `completed_at` nullable
- `budget_amount` nullable
- `budget_hours` nullable
- `actual_cost_amount` default 0
- `actual_billable_amount` default 0
- `progress_percent` default 0
- `health_status` (`on_track`, `at_risk`, `off_track`)
- `created_by`, `updated_by`
- timestamps + soft deletes

Indexes:

- `(company_id, status)`
- `(company_id, customer_id)`
- `(company_id, project_manager_id)`
- unique `(company_id, project_code)`

### 7.2 `project_members`

Purpose: users assigned to the project.

Suggested columns:

- `id` UUID
- `company_id`
- `project_id`
- `user_id`
- `project_role` (`manager`, `member`, `reviewer`, `billing_owner`)
- `allocation_percent` nullable
- `hourly_cost_rate` nullable
- `hourly_bill_rate` nullable
- `is_billable_by_default` boolean
- timestamps

Unique index:

- `(project_id, user_id)`

### 7.3 `project_stages`

Purpose: configurable task stages per company/project type.

Suggested columns:

- `id` UUID
- `company_id`
- `name`
- `sequence`
- `color` nullable
- `is_closed_stage` boolean
- `created_by`, `updated_by`
- timestamps

### 7.4 `project_tasks`

Purpose: work items within projects.

Suggested columns:

- `id` UUID
- `company_id`
- `project_id`
- `stage_id` nullable -> `project_stages.id`
- `parent_task_id` nullable -> `project_tasks.id`
- `customer_id` nullable
- `title`
- `description` nullable
- `task_number`
- `status` (`draft`, `todo`, `in_progress`, `blocked`, `review`, `done`, `cancelled`)
- `priority` (`low`, `medium`, `high`, `critical`)
- `assigned_to` nullable -> `users.id`
- `start_date` nullable
- `due_date` nullable
- `completed_at` nullable
- `estimated_hours` nullable
- `actual_hours` default 0
- `is_billable` boolean
- `billing_status` (`not_ready`, `ready`, `billed`, `non_billable`)
- `created_by`, `updated_by`
- timestamps + soft deletes

Indexes:

- `(company_id, project_id, status)`
- `(company_id, assigned_to, status)`
- unique `(company_id, task_number)`

### 7.5 `project_timesheets`

Purpose: time entries logged against tasks/projects.

Suggested columns:

- `id` UUID
- `company_id`
- `project_id`
- `task_id` nullable
- `user_id`
- `work_date`
- `description` nullable
- `hours`
- `is_billable`
- `cost_rate`
- `bill_rate`
- `cost_amount`
- `billable_amount`
- `approval_status` (`draft`, `submitted`, `approved`, `rejected`)
- `approved_by` nullable -> `users.id`
- `approved_at` nullable
- `rejection_reason` nullable
- `invoice_status` (`not_ready`, `ready`, `invoiced`, `non_billable`)
- `source_type` nullable
- `source_id` nullable
- timestamps + soft deletes

Indexes:

- `(company_id, project_id, work_date)`
- `(company_id, user_id, work_date)`
- `(company_id, approval_status)`
- `(company_id, invoice_status)`

### 7.6 `project_milestones`

Purpose: fixed-fee or delivery checkpoints.

Suggested columns:

- `id` UUID
- `company_id`
- `project_id`
- `name`
- `description` nullable
- `sequence`
- `status` (`draft`, `in_progress`, `ready_for_review`, `approved`, `billed`, `cancelled`)
- `due_date` nullable
- `completed_at` nullable
- `approved_at` nullable
- `approved_by` nullable -> `users.id`
- `amount`
- `invoice_status` (`not_ready`, `ready`, `invoiced`)
- `created_by`, `updated_by`
- timestamps

### 7.7 `project_billables`

Purpose: normalized queue of items ready or nearly ready to invoice.

Suggested columns:

- `id` UUID
- `company_id`
- `project_id`
- `billable_type` (`timesheet`, `milestone`, `expense`, `material`, `manual`)
- `source_type`
- `source_id`
- `customer_id`
- `description`
- `quantity`
- `unit_price`
- `amount`
- `currency_id`
- `status` (`draft`, `ready`, `approved`, `invoiced`, `cancelled`)
- `approval_status` (`not_required`, `pending`, `approved`, `rejected`)
- `invoice_id` nullable -> accounting invoice table
- `invoice_line_reference` nullable
- `approved_by` nullable -> `users.id`
- `approved_at` nullable
- `created_by`, `updated_by`
- timestamps

Indexes:

- `(company_id, project_id, status)`
- `(company_id, customer_id, status)`
- `(source_type, source_id)`

### 7.8 Optional phase-two tables

Only add these after Phase 1 is stable:

- `project_updates`
- `project_templates`
- `project_checklists`
- `project_expenses`
- `project_worksheets`
- `project_plans` / scheduling tables

## 8) Eloquent Models

Create these under `app/Modules/Projects/Models`:

- `Project`
- `ProjectMember`
- `ProjectStage`
- `ProjectTask`
- `ProjectTimesheet`
- `ProjectMilestone`
- `ProjectBillable`

Model requirements:

- UUID primary keys
- `CompanyScoped` behavior consistent with existing modules
- `created_by` / `updated_by` support where relevant
- clear relationships to Core and Accounting/Sales models
- status constants or enums matching existing module style

Key relationships:

- `Project` belongs to company/customer/sales order/project manager
- `Project` has many tasks, timesheets, milestones, members, billables
- `ProjectTask` belongs to project/stage/assignee
- `ProjectTimesheet` belongs to project/task/user/approver
- `ProjectBillable` morphs back to its source record

## 9) Services Layer

Create domain services under `app/Modules/Projects`.

### Required services

#### `ProjectProvisioningService`

Responsibilities:

- create project manually
- create project from confirmed sales order/service contract
- seed default stages and default member roles
- initialize budget/manager/customer context

#### `ProjectTaskWorkflowService`

Responsibilities:

- task status transitions
- stage movement rules
- completion rules
- automatic roll-up of project progress and actual hours

#### `ProjectTimesheetService`

Responsibilities:

- create/update/delete time entries
- apply default cost/bill rates from project member profile
- prevent logging outside allowed project/task state
- maintain task and project actual hours totals

#### `ProjectTimesheetApprovalService`

Responsibilities:

- submit/approve/reject timesheets
- integrate with Approvals when thresholds or overrides apply
- stamp approval metadata
- determine invoice readiness

#### `ProjectMilestoneService`

Responsibilities:

- create/update milestones
- mark ready for review
- approve/reject milestone completion
- emit billable-ready signals

#### `ProjectBillingService`

Responsibilities:

- normalize approved timesheets/milestones into `project_billables`
- aggregate ready items by project/customer
- create draft customer invoices in Accounting
- mark billables as invoiced once invoice draft is generated
- support fixed-fee, time-and-material, and mixed billing logic

#### `ProjectProfitabilityService`

Responsibilities:

- compute planned vs actual hours
- compute planned vs actual revenue/cost
- surface margin and utilization KPIs
- feed dashboard/report payloads

### Optional later services

- `ProjectTemplateService`
- `ProjectWorksheetService`
- `ProjectSchedulingService`
- `ProjectExpenseRebillingService`

## 10) Policies and Authorization

Create policies similar to existing modules.

### Policies to add

- `ProjectPolicy`
- `ProjectTaskPolicy`
- `ProjectTimesheetPolicy`
- `ProjectMilestonePolicy`
- `ProjectBillablePolicy`

### Authorization rules

- owner has full company access
- project managers can manage assigned projects
- project users can view/update tasks and timesheets only within permitted data scope
- finance users can view billing queue and create invoices if they also have accounting permissions
- approvers can approve billable/timesheet exceptions without becoming project editors by default

### Record-scope expectations

Use the same scope model already introduced in Port-101:

- `own_records`
- `team_records`
- `company_records`
- `read_all`

Project-specific interpretation:

- own = records assigned to or created by current user
- team = records inside projects where current user is a manager/member of the same team
- company = all projects in active company
- read_all = read-only across company/project data

## 11) Web Routes and Controllers

Create a new route file:

- `routes/moduleroutes/projects.php`

Suggested route prefix:

- `/company/projects`

### Page/controller plan

- `ProjectsDashboardController@index`
  - `/company/projects`
- `ProjectsController@index`
  - `/company/projects/all`
- `ProjectsController@create`
  - `/company/projects/create`
- `ProjectsController@store`
- `ProjectsController@show`
  - `/company/projects/{project}`
- `ProjectsController@edit`
- `ProjectsController@update`
- `ProjectsTasksController@store/update/destroy`
- `ProjectsTasksWorkflowController`
  - start, block, review, complete, reopen
- `ProjectsTimesheetsController@index/store/update/destroy`
- `ProjectsTimesheetApprovalController`
  - submit, approve, reject
- `ProjectsMilestonesController@index/store/update`
- `ProjectsMilestoneWorkflowController`
  - mark-ready, approve, reject
- `ProjectsBillablesController@index`
- `ProjectsBillingController@createInvoiceDraft`

### Route protection

Use:

- company workspace middleware
- module-level permission middleware for `projects.*`
- policy checks in controllers for project-scoped records

## 12) API Plan (`/api/v1`)

Add API support only where external systems actually benefit.

Create API endpoints under `/api/v1/projects` protected by Sanctum.

### Phase-1 API endpoints

- `GET /api/v1/projects`
- `GET /api/v1/projects/{project}`
- `GET /api/v1/projects/{project}/tasks`
- `POST /api/v1/projects/{project}/tasks`
- `GET /api/v1/projects/{project}/timesheets`
- `POST /api/v1/projects/{project}/timesheets`
- `POST /api/v1/projects/timesheets/{timesheet}/submit`
- `GET /api/v1/projects/{project}/billables`

### API design rules

- company-scoped by active token user/company context
- no cross-company references in payloads
- use request validation classes mirroring web flows
- avoid exposing accounting mutation endpoints here in Phase 1 except invoice-draft trigger if explicitly needed

### Request classes to add

- `ProjectStoreRequest`
- `ProjectUpdateRequest`
- `ProjectTaskStoreRequest`
- `ProjectTaskUpdateRequest`
- `ProjectTimesheetStoreRequest`
- `ProjectTimesheetUpdateRequest`
- `ProjectMilestoneStoreRequest`
- `ProjectBillableInvoiceRequest`

## 13) UI / Inertia Plan

Create pages under `resources/js/pages/projects`.

### Core pages

- `resources/js/pages/projects/index.tsx`
  - dashboard-first landing page
- `resources/js/pages/projects/projects/index.tsx`
  - full project list
- `resources/js/pages/projects/projects/create.tsx`
- `resources/js/pages/projects/projects/edit.tsx`
- `resources/js/pages/projects/projects/show.tsx`
- `resources/js/pages/projects/timesheets/index.tsx`
- `resources/js/pages/projects/billables/index.tsx`

### Project show page tabs

The project detail page should be the central workspace.

Recommended tabs:

- `Overview`
- `Tasks`
- `Timesheets`
- `Milestones`
- `Billing`
- `Files`
- `Activity`

### UX requirements

- modern dashboard layout, not a CRUD-first wall of tables
- KPI cards for budget, actual hours, billable amount, margin, overdue tasks
- Kanban + list toggle for tasks
- weekly timesheet grid for fast entry
- billing queue cards/table with status chips and invoice readiness
- attachments panel reused from core attachments module
- recent activity feed backed by audit/events
- charts for progress, hours burn, billable pipeline, and margin trend

### Sidebar/navigation

Add a new module entry:

- `Projects`

Suggested sub-navigation:

- Dashboard
- Projects
- Timesheets
- Billing

## 14) Workflow Design

### A. Manual project creation flow

1. User creates project.
2. Default stages are attached.
3. Members are assigned.
4. Tasks and milestones are added.
5. Team logs time or completes milestones.
6. Billable items are reviewed.
7. Accounting invoice draft is created.

### B. Sales-driven project flow

1. Sales order with service product is confirmed.
2. `ProjectProvisioningService` creates project or attaches to existing project template.
3. Customer, sold hours, sold value, and project manager defaults are carried over.
4. Team executes tasks and logs time.
5. Approved time/milestones become billables.
6. Billables generate accounting invoice draft.

### C. Time-and-material billing flow

1. User logs time.
2. Timesheet is submitted.
3. Manager approves.
4. `ProjectBillingService` converts approved time to billable entries.
5. Finance/project billing owner generates invoice draft.
6. Accounting posts and reconciles invoice/payment.

### D. Fixed-fee milestone flow

1. Milestone is created with value.
2. Project manager marks it ready for review.
3. Reviewer/approver approves milestone.
4. Billable queue receives fixed-fee billable line.
5. Invoice draft is created in Accounting.

## 15) Accounting Integration Rules

This module should not post directly to the ledger.

It should only:

- create accounting invoice drafts
- link invoice IDs back to project billables
- read invoice/payment state for status display

Recommended integration fields:

- `projects.sales_order_id`
- `project_billables.invoice_id`
- optional `project_id` reference on accounting invoice headers/lines for reporting

Important implementation decision:

Before adding full analytic accounting, keep project-to-invoice linkage explicit with `project_id` and `source_type/source_id` references. That is simpler and matches Port-101's current maturity better than introducing a full analytic ledger layer immediately.

## 16) Sales Integration Rules

Sales should be optional but strongly integrated.

Recommended behavior:

- service products in Sales can be marked `creates_project`
- service products can declare billing mode: `fixed_fee`, `time_and_material`, `milestone`
- confirmed sales orders can create:
  - one project per order, or
  - one project task inside an existing project template

Port-101 should keep this initial implementation simple:

- one confirmed sales order -> one project
- sold customer and sales-order reference copied into the project
- no complex contract/amendment logic in Phase 1

## 17) Reports Needed

Add company reports for:

- project pipeline / active projects
- overdue tasks
- timesheet approval backlog
- billable queue aging
- project profitability summary
- utilization by user / team
- milestone completion status
- invoiced vs uninvoiced service work

Export formats should follow existing standards:

- PDF via branded Blade templates
- XLSX via tabular exports

## 18) Notifications and Attachments

### Notifications

Use current notification infrastructure for:

- task assignment
- overdue tasks
- timesheet approval required
- milestone ready for review
- billable approved or rejected
- invoice draft created for project billables

### Attachments

Use current attachments module for:

- project documents
- task files
- statement of work / contracts
- milestone deliverables
- client sign-off evidence

## 19) Testing Plan

### Feature tests

- project CRUD and company scoping
- project-member permission boundaries
- task workflow transitions
- timesheet create/update/submit/approve/reject
- milestone review/approval flow
- billable generation from approved timesheets and milestones
- invoice draft creation in Accounting from project billables
- sales-order-driven project provisioning

### Authorization tests

- project manager vs project user vs finance manager vs approver vs owner
- record-scope behavior for own/team/company access
- forbidden access across company boundaries

### API tests

- token-auth access to project/task/timesheet endpoints
- company-scoped validation for API mutations

### UI regression targets

- dashboard payload integrity
- project detail tab data
- billing queue totals

## 20) Delivery Sequence

### Phase 1: Foundation

- migrations and models
- permissions and policies
- project list/create/show/edit
- task management
- basic dashboard KPIs

Exit criteria:

- users can manage projects and tasks safely within company scope

### Phase 2: Timesheets and milestones

- time entry UI
- approval flow
- milestone flow
- project progress rollups

Exit criteria:

- project effort and milestone completion are tracked end-to-end

### Phase 3: Billing integration

- billable queue
- invoice-draft generation
- profitability dashboard/reporting
- sales-order project provisioning

Exit criteria:

- approved service work turns into accounting invoice drafts reliably

### Phase 4: Hardening and optional expansion

- templates
- customer collaboration options
- field-work worksheets
- rebillable expenses/materials
- planning/scheduling

## 21) Recommended File Structure

When implementation starts, use:

- `app/Modules/Projects/Models/*`
- `app/Modules/Projects/*Service.php`
- `app/Http/Controllers/Projects/*`
- `app/Http/Requests/Projects/*`
- `app/Policies/Project*.php`
- `routes/moduleroutes/projects.php`
- `resources/js/pages/projects/*`
- `tests/Feature/Projects/*`

## 22) Recommended Next Implementation Order

When you approve this plan, the build order should be:

1. schema + models
2. permissions + policies + seeded roles
3. project/task routes/controllers/pages
4. timesheets + approvals
5. milestones + billables
6. accounting invoice-draft handoff
7. sales-order provisioning integration
8. reports and profitability layer

## References

This plan is informed by current official Odoo service-delivery patterns:

- Odoo Project management: https://www.odoo.com/documentation/19.0/applications/services/project/project_management.html
- Odoo Timesheets / time-based invoicing: https://www.odoo.com/documentation/19.0/applications/sales/sales/invoicing/time_materials.html
- Odoo Field Service overview: https://www.odoo.com/documentation/19.0/applications/services/field_service.html
- Odoo Field Service worksheets: https://www.odoo.com/documentation/19.0/applications/services/field_service/worksheets.html
