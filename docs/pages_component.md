# Pages And Components Map

## Purpose

This document is a design-inventory map of the current React/Inertia frontend.

It is meant to answer these questions before a redesign:

- what page surfaces exist
- what each page family contains
- which components are reused across many pages
- which patterns are repeated inline instead of being centralized
- which areas can be redesigned once and then propagated everywhere

This is a frontend structure map, not a backend route or service map.

Primary source folders:

- `resources/js/pages`
- `resources/js/components`
- `resources/js/layouts`

## 1. Global Shell

### 1.1 Main authenticated shell

Most app pages use `AppLayout`, which resolves to:

- `resources/js/layouts/app-layout.tsx`
- `resources/js/layouts/app/app-sidebar-layout.tsx`

Shared chrome inside that shell:

- `AppShell`
- `AppSidebar`
- `AppSidebarHeader`
- `Breadcrumbs`
- header/company/platform context
- page content container with standard horizontal padding

Design impact:

- changing the shell, spacing rhythm, header density, sidebar style, and breadcrumb treatment will affect almost every authenticated page

### 1.2 Navigation structure

Shared navigation is defined in:

- `resources/js/components/app-sidebar.tsx`

Main sidebar groups:

- Company
- Modules
- Master Data
- Governance
- Platform Admin

This file is one of the highest leverage redesign targets because it controls:

- navigation labels
- icon system
- section ordering
- badge placement
- information density

### 1.3 Header and user controls

Header-related shared pieces:

- `resources/js/components/app-header.tsx`
- `resources/js/components/company-switcher.tsx`
- `resources/js/components/nav-user.tsx`
- `resources/js/components/user-menu-content.tsx`

Current shared header patterns:

- avatar/user menu
- notification entry point
- company switcher
- breadcrumbs on pages that provide more than one breadcrumb item

### 1.4 Flash and feedback layer

Shared feedback components:

- `resources/js/components/flash-toaster.tsx`
- `resources/js/components/ui/toast.tsx`
- `resources/js/components/alert-error.tsx`
- `resources/js/components/input-error.tsx`

These affect nearly all forms and workflow actions.

## 2. Auth And Public Layouts

Auth/public pages do not use the full sidebar shell.

Primary layout files:

- `resources/js/layouts/auth-layout.tsx`
- `resources/js/layouts/auth/auth-simple-layout.tsx`
- `resources/js/layouts/auth/auth-card-layout.tsx`
- `resources/js/layouts/auth/auth-split-layout.tsx`

Public/auth pages:

- `resources/js/pages/welcome.tsx`
- `resources/js/pages/auth/login.tsx`
- `resources/js/pages/auth/forgot-password.tsx`
- `resources/js/pages/auth/reset-password.tsx`
- `resources/js/pages/auth/confirm-password.tsx`
- `resources/js/pages/auth/verify-email.tsx`
- `resources/js/pages/auth/two-factor-challenge.tsx`
- `resources/js/pages/invites/accept.tsx`

Shared auth components:

- `TextLink`
- `AlertError`
- `Button`
- `Input`
- `InputOtp`
- `Label`
- two-factor setup/recovery components

Design impact:

- auth redesign can be handled largely independently from the main app shell

## 3. Shared UI Primitives

The base design system lives in `resources/js/components/ui`.

Most reused primitives:

- `button.tsx`
- `card.tsx`
- `badge.tsx`
- `input.tsx`
- `label.tsx`
- `checkbox.tsx`
- `select.tsx`
- `alert.tsx`
- `dialog.tsx`
- `dropdown-menu.tsx`
- `sheet.tsx`
- `sidebar.tsx`
- `tooltip.tsx`
- `avatar.tsx`
- `breadcrumb.tsx`
- `separator.tsx`
- `skeleton.tsx`
- `spinner.tsx`

Current page families mostly build on these primitives directly rather than on a deeper app-specific component layer.

Design implication:

- visual redesign is feasible through primitive restyling
- structural redesign will require more work because many page compositions are still inline in the page files

## 4. Shared App-Level Components

These are reusable but more domain-aware than the UI primitives.

### 4.1 Record and file components

- `resources/js/components/attachments-panel.tsx`

Used in record-detail and edit flows where file upload/download/delete exists.

Current pattern:

- panel title
- upload control
- attachment table
- download/delete actions

### 4.2 Chart components

Company dashboard:

- `resources/js/components/company/dashboard/activity-trend-chart.tsx`
- `resources/js/components/company/dashboard/invite-status-chart.tsx`

Platform dashboard:

- `resources/js/components/platform/dashboard/delivery-trend-chart.tsx`
- `resources/js/components/platform/dashboard/delivery-status-donut.tsx`
- `resources/js/components/platform/dashboard/governance-time-series-chart.tsx`
- `resources/js/components/platform/dashboard/noisy-events-chart.tsx`

These are reusable within their dashboard families but not yet generalized into one chart system.

### 4.3 Editor components

These power line-entry or structured-input workflows:

- `resources/js/components/sales/line-items-editor.tsx`
- `resources/js/components/purchasing/line-items-editor.tsx`
- `resources/js/components/accounting/invoice-line-items-editor.tsx`
- `resources/js/components/accounting/manual-journal-lines-editor.tsx`
- `resources/js/components/inventory/inventory-move-lines-editor.tsx`
- `resources/js/components/integrations/webhook-endpoint-form.tsx`

These are high-value redesign targets because they define table-like data-entry UX.

### 4.4 Settings and profile helpers

- `resources/js/components/appearance-tabs.tsx`
- `resources/js/components/two-factor-setup-modal.tsx`
- `resources/js/components/two-factor-recovery-codes.tsx`

## 5. Cross-Page Structural Patterns

These patterns repeat across the app.

### 5.1 Index/List pages

Common structure:

- page title + short description
- top-right CTA buttons
- filter controls inline inside the page
- card or bordered container
- horizontally scrollable table
- action buttons per row

This pattern appears in:

- core master-data indexes
- platform companies/admin-users/invites
- sales lists
- inventory lists
- purchasing lists
- accounting lists
- projects lists
- HR lists
- integrations lists

### 5.2 Create/Edit forms

Common structure:

- page header with title and back link
- vertical form sections
- labels + inputs + `InputError`
- native `select` elements are still used in many places
- submit/cancel footer actions

This pattern appears across nearly every create/edit page.

### 5.3 Show/Detail pages

Common structure:

- summary cards or status badges
- sections stacked vertically
- tables for related records
- action button cluster near the top
- attachments/activity blocks on some domains

Examples:

- project detail
- employee detail
- platform company detail
- lot/serial detail
- cycle count detail
- payroll run detail

### 5.4 Dashboard pages

Common structure:

- hero/summary header
- KPI cards
- chart sections
- quick actions
- recent activity blocks

Dashboard families:

- company dashboard
- platform dashboard
- module dashboards: sales, inventory, purchasing, accounting, projects, HR

## 6. Page Inventory By Area

## 6.1 Company shell and workspace pages

Pages:

- `resources/js/pages/company/dashboard.tsx`
- `resources/js/pages/company/settings.tsx`
- `resources/js/pages/company/users.tsx`
- `resources/js/pages/company/roles.tsx`
- `resources/js/pages/company/inactive.tsx`
- `resources/js/pages/company/module-placeholder.tsx`

What they contain:

- dashboard: KPI cards, activity chart, invite-status chart, quick-action cards, recent activity, master-data breakdown
- settings: company profile/defaults/settings form panels
- users: read-only active-user directory linked back to HR employees
- roles: role matrix / role-management table patterns
- inactive: status page for blocked company access
- module placeholder: legacy placeholder shell for incomplete module surfaces

Shared components:

- `AppLayout`
- `Button`
- `Link`
- bordered cards
- tables
- badge/status patterns

## 6.2 Settings pages

Pages:

- `resources/js/pages/settings/profile.tsx`
- `resources/js/pages/settings/password.tsx`
- `resources/js/pages/settings/two-factor.tsx`
- `resources/js/pages/settings/appearance.tsx`
- `resources/js/pages/settings/dashboard-personalization.tsx`

What they contain:

- profile and password forms
- two-factor controls and recovery states
- appearance controls/tabs
- dashboard personalization toggles and checkbox-driven preferences

Shared components:

- settings layout
- form primitives
- toggle/checkbox controls
- two-factor helper components

## 6.3 Platform admin pages

Pages:

- `resources/js/pages/platform/dashboard.tsx`
- `resources/js/pages/platform/governance.tsx`
- `resources/js/pages/platform/reports.tsx`
- `resources/js/pages/platform/operations/queue-health.tsx`
- `resources/js/pages/platform/companies/index.tsx`
- `resources/js/pages/platform/companies/create.tsx`
- `resources/js/pages/platform/companies/show.tsx`
- `resources/js/pages/platform/admin-users/index.tsx`
- `resources/js/pages/platform/admin-users/create.tsx`
- `resources/js/pages/platform/invites/index.tsx`
- `resources/js/pages/platform/invites/create.tsx`

What they contain:

- dashboard: KPI cards, multiple charts, quick actions, operations tabs, status cards
- governance: policy settings panels, analytics summaries, threshold controls
- reports: export/report center with filters, preset handling, report cards
- queue health: operational tables, retry/discard actions, poison-message visibility
- companies: company registry table, create form, detail and status controls
- admin users: active admins plus pending invite states
- platform invites: platform/company invite issue and management tables

Shared components:

- platform dashboard chart components
- tables with action buttons
- filter bars
- card grids
- badges for status/severity/delivery

Design note:

- the platform dashboard and governance pages are the densest pages in the app

## 6.4 Core business-support pages

Pages:

- `resources/js/pages/core/partners/*`
- `resources/js/pages/core/contacts/*`
- `resources/js/pages/core/addresses/*`
- `resources/js/pages/core/products/*`
- `resources/js/pages/core/taxes/*`
- `resources/js/pages/core/currencies/*`
- `resources/js/pages/core/uoms/*`
- `resources/js/pages/core/price-lists/*`
- `resources/js/pages/core/audit-logs/index.tsx`
- `resources/js/pages/core/invites/index.tsx`
- `resources/js/pages/core/invites/create.tsx`
- `resources/js/pages/core/notifications/index.tsx`

What they contain:

- master-data pages: consistent CRUD list/create/edit pattern
- products: richer form because of bundle settings and catalog fields
- audit logs: filter-heavy table with export/delete controls
- owner invites: owner-only invite management
- notifications: notification center list/feed with unread and lifecycle actions

Shared components:

- `AttachmentsPanel` on many edit pages
- standard CRUD forms
- standard table shells
- filters inline in page files

Design note:

- core CRUD pages are the easiest place to introduce a unified list-page and form-page pattern

## 6.5 Sales pages

Pages:

- `resources/js/pages/sales/index.tsx`
- `resources/js/pages/sales/leads/index.tsx`
- `resources/js/pages/sales/leads/create.tsx`
- `resources/js/pages/sales/leads/edit.tsx`
- `resources/js/pages/sales/quotes/index.tsx`
- `resources/js/pages/sales/quotes/create.tsx`
- `resources/js/pages/sales/quotes/edit.tsx`
- `resources/js/pages/sales/orders/index.tsx`
- `resources/js/pages/sales/orders/create.tsx`
- `resources/js/pages/sales/orders/edit.tsx`

What they contain:

- sales dashboard summary
- list tables for leads, quotes, orders
- line-item entry forms for quotes/orders
- approval and workflow action buttons

Shared sales-specific component:

- `resources/js/components/sales/line-items-editor.tsx`

## 6.6 Inventory pages

Pages:

- `resources/js/pages/inventory/index.tsx`
- `resources/js/pages/inventory/warehouses/*`
- `resources/js/pages/inventory/locations/*`
- `resources/js/pages/inventory/stock-levels/index.tsx`
- `resources/js/pages/inventory/moves/*`
- `resources/js/pages/inventory/lots/index.tsx`
- `resources/js/pages/inventory/lots/show.tsx`
- `resources/js/pages/inventory/cycle-counts/*`
- `resources/js/pages/inventory/reordering/*`

What they contain:

- inventory dashboard summary
- warehouse/location CRUD
- stock tables
- stock move forms and lifecycle actions
- lot/serial tracking detail
- cycle count session creation/review/posting
- reorder rules and replenishment suggestions

Shared inventory-specific component:

- `resources/js/components/inventory/inventory-move-lines-editor.tsx`

Design note:

- inventory mixes admin CRUD, operational workflows, and traceability detail in the same visual language

## 6.7 Purchasing pages

Pages:

- `resources/js/pages/purchasing/index.tsx`
- `resources/js/pages/purchasing/rfqs/*`
- `resources/js/pages/purchasing/orders/*`

What they contain:

- purchasing dashboard summary
- RFQ tables and forms
- purchase-order tables and forms
- workflow actions for approval, confirm, receive

Shared purchasing-specific component:

- `resources/js/components/purchasing/line-items-editor.tsx`

## 6.8 Accounting pages

Pages:

- `resources/js/pages/accounting/index.tsx`
- `resources/js/pages/accounting/invoices/*`
- `resources/js/pages/accounting/payments/*`
- `resources/js/pages/accounting/manual-journals/*`
- `resources/js/pages/accounting/bank-reconciliation/index.tsx`
- `resources/js/pages/accounting/accounts/index.tsx`
- `resources/js/pages/accounting/journals/index.tsx`
- `resources/js/pages/accounting/ledger/index.tsx`
- `resources/js/pages/accounting/statements/index.tsx`

What they contain:

- finance dashboard summary
- invoice/payment CRUD and workflow actions
- manual-journal creation and approval state
- bank-statement import/reconciliation workflow
- accounts/journals/ledger/statement reporting tables

Shared accounting-specific components:

- `resources/js/components/accounting/invoice-line-items-editor.tsx`
- `resources/js/components/accounting/manual-journal-lines-editor.tsx`

Design note:

- accounting pages are among the most table-heavy and workflow-dense pages in the product

## 6.9 Projects pages

Pages:

- `resources/js/pages/projects/index.tsx`
- `resources/js/pages/projects/projects/*`
- `resources/js/pages/projects/tasks/*`
- `resources/js/pages/projects/timesheets/*`
- `resources/js/pages/projects/milestones/*`
- `resources/js/pages/projects/billables/index.tsx`
- `resources/js/pages/projects/recurring-billing/*`

What they contain:

- projects dashboard with KPI/profitability signals
- project workspace list
- project detail page with summary, members, tasks, timesheets, milestones, billables, invoices, files, activity
- task/timesheet/milestone CRUD
- billing queue
- recurring billing schedule management

Shared patterns:

- show-page summary cards
- related-record tables
- attachments/activity sections
- workflow action clusters

Design note:

- project detail is one of the most information-dense show pages and a strong candidate for tab or section redesign

## 6.10 HR pages

Pages:

- `resources/js/pages/hr/index.tsx`
- `resources/js/pages/hr/employees/*`
- `resources/js/pages/hr/leave/*`
- `resources/js/pages/hr/attendance/*`
- `resources/js/pages/hr/reimbursements/*`
- `resources/js/pages/hr/payroll/*`
- `resources/js/pages/hr/reports/index.tsx`

What they contain:

- HR dashboard
- employee CRUD and employee detail
- employee access lifecycle management
- leave setup and request flows
- attendance shift/assignment/request flows
- reimbursement claim and category flows
- payroll setup, runs, payslips
- HR reports center

Embedded HR-specific form components:

- `hr/reimbursements/claims/claim-form.tsx`
- `hr/payroll/assignments/assignment-form.tsx`
- `hr/payroll/periods/period-form.tsx`
- `hr/payroll/structures/structure-form.tsx`

Design note:

- HR is now one of the largest page families and would benefit from a clearer visual subsystem of its own

## 6.11 Approvals and company reports

Pages:

- `resources/js/pages/approvals/index.tsx`
- `resources/js/pages/reports/index.tsx`

What they contain:

- approvals queue table with filters and action buttons
- report cards, export actions, preset controls, scheduling controls

These are simple top-level modules structurally but high-frequency operational surfaces.

## 6.12 Integrations pages

Pages:

- `resources/js/pages/integrations/index.tsx`
- `resources/js/pages/integrations/webhooks/*`
- `resources/js/pages/integrations/deliveries/*`

What they contain:

- integrations dashboard summary
- webhook endpoint CRUD
- delivery-history queue
- delivery detail and retry state

Shared integrations-specific component:

- `resources/js/components/integrations/webhook-endpoint-form.tsx`

## 7. Design System Reuse Summary

If redesign work starts, these are the most reused building blocks to standardize first.

### Highest-leverage shared primitives

- `Button`
- `Input`
- `Label`
- `Badge`
- `Card`
- `Alert`
- `DropdownMenu`
- `Sheet`
- `Tooltip`
- `Sidebar`
- `Avatar`

### Highest-leverage app components

- `AppLayout` shell
- `AppSidebar`
- `AppHeader`
- `Breadcrumbs`
- `AttachmentsPanel`
- chart family components
- line-entry editors

### Highest-leverage page patterns

- list page header + filter + table pattern
- create/edit form page pattern
- KPI dashboard card pattern
- detail/show page section pattern

## 8. Design Update Opportunities

These are the most obvious places where the current app can be unified or simplified during a redesign.

### 8.1 List-page standardization

Current state:

- many index pages hand-roll the same structure
- filters are mostly inline
- tables are repeated with small variations

Opportunity:

- create one reusable list-page frame with:
  - title area
  - actions slot
  - filters slot
  - table slot
  - empty state
  - pagination footer

### 8.2 Form-page standardization

Current state:

- create/edit pages repeat the same form layout manually

Opportunity:

- create a shared form-shell pattern with:
  - header
  - section cards
  - field grid tokens
  - sticky action footer

### 8.3 Dashboard consistency

Current state:

- company, platform, and module dashboards share concepts but not a formal dashboard component system

Opportunity:

- standardize:
  - KPI card variants
  - chart card layout
  - quick-action card style
  - activity feed card style

### 8.4 Status language

Current state:

- many pages use badges, text colors, and inline labels slightly differently

Opportunity:

- define a central status/badge system for:
  - pending
  - approved
  - rejected
  - active
  - inactive
  - failed
  - delivered
  - posted
  - draft

### 8.5 Module visual identity

Current state:

- modules are functionally distinct, but visually they still mostly share the same generic bordered-card/table style

Opportunity:

- keep one global system, but give each major module a clearer visual emphasis through:
  - icon treatment
  - accent usage
  - dashboard composition
  - section hierarchy

## 9. Redesign Priority Order

If the goal is to refresh the whole app without rewriting everything at once, the best order is:

1. app shell
2. shared primitives
3. list-page system
4. form-page system
5. dashboard system
6. dense show pages
7. module-specific refinements

That order gives the largest visual improvement with the least disruption.
