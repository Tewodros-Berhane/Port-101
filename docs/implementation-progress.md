# Implementation Progress

## Completed

- Core foundation: auth, RBAC, multi-company, UUIDs, company scoping.
- Master data schema: migrations for partners, contacts, addresses, uoms, currencies, taxes, products, price_lists.
- Master data models: Partner, Contact, Address, Uom, Currency, Tax, Product, PriceList with UUIDs, CompanyScoped, soft deletes, created_by/updated_by.
- Master data CRUD: controllers, requests, Inertia pages, and routes for partners, contacts, addresses, products, taxes, currencies, uoms, price lists.
- Contacts and addresses permissions: `core.contacts.*` and `core.addresses.*` seeded and enforced via policies.
- Permissions: per-entity permissions seeded; owner role gets all permissions; legacy core.master_data.manage removed.
- Authorization wiring: policies for Partner/Contact/Address/Product/Tax/Currency/Uom/PriceList; AuthServiceProvider registers them; controllers call `$this->authorize()` for CRUD actions; base Controller uses AuthorizesRequests.
- Access rules: super admin is view-only for master data; company owners (is_owner) bypass and get full access.
- Tests: feature test covering master data policy behavior for super admin view-only and owner bypass.
- Master data UI gating: `.view` controls nav/list visibility, `.manage` controls create/edit/delete actions.
- Route-level permission tests for master data endpoints (403/200 flows).
- Audit log storage and event hooks for master data create/update/delete.
- Audit log permissions, policy, and list page.
- Audit log filters and export actions.
- Audit log delete actions.
- Audit log filter/export tests.
- Platform mode seeding: DatabaseSeeder creates a super admin only (no default company).
- Superadmin access middleware and platform dashboard.
- Platform company registry list, create flow, and detail page, including editable `is_active` status.
- Platform admin user management (platform admins/support accounts).
- Platform invite management (issue invites, list, revoke).
- Invite-only direction applied: public registration disabled and register links removed.
- Role-based dashboard routing: superadmin -> `/platform/dashboard`, company users -> `/company/dashboard`.
- Sidebar/navigation cleanup: role-aware links, section spacing/icons, starter links removed.
- Breadcrumb hierarchy added for Master Data and Governance pages.
- Branding alignment: Port-101 logo/name unified across sidebar, header, and auth layouts.
- Light mode border visibility improved with stronger border tokens.
- Global toast notifications added for success/error/warning flash messages.
- Invite acceptance flow (`/invites/{token}`) with token states (invalid/expired/accepted).
- Invite-driven provisioning: accepted invites create users and assign platform/company roles.
- Company-context invite management added (create/list/resend/revoke for users with `core.users.manage`).
- Invite email delivery and resend actions wired.
- Invite flow feature tests added (acceptance, token states, company invite permissions).
- Company owner workspace foundation: settings, users, roles pages.
- Company module placeholders added in sidebar/routes (Sales, Inventory, Purchasing, Accounting, Approvals, Reports).
- Company users role assignment action added (per-member role updates from `/company/users`, with owner-role protection).
- Company status safeguards enforced (inactive company selection blocked, active company context auto-resolution, inactive company access blocked for non-superadmins).
- Platform dashboard activity widgets added (recent invites and recent superadmin actions).
- Invitation delivery hardening completed (queued delivery job, retry attempts, failure status/error visibility, manual retry actions in platform/company invite lists).
- Company suspension lifecycle UX polish completed (dedicated inactive-company page, redirect-based inactive access flow, and inactive selection messaging).
- Platform operations reporting improvements completed (delivery trend metrics and admin-action filtering on platform dashboard).
- Superadmin operations reporting exports implemented (filtered admin actions + delivery trends in CSV/JSON).
- Audit log retention rules implemented (config + settings-backed pruning command + scheduler).
- Core API scaffolding implemented under `/api/v1` (health + partners/products/settings endpoints).
- Core settings persistence layer implemented (`settings` table/model/service + company settings integration).
- Attachments/media module implemented (schema/model/policy/controller + partner/product UI integration).
- In-app notifications module implemented (database notifications center, unread counters, mark-read actions, event notifications beyond invite email).
- Notification governance controls implemented (severity threshold, escalation policy, digest scheduling policy + digest command).
- Legacy auth/settings/dashboard tests aligned with invite-only and active-company middleware behavior (including active-company test helper updates).
- Company-scoped foreign-key validation hardening implemented for core request references (`partner_id`, `uom_id`, `default_tax_id`, `currency_id`) with coverage tests.
- Attachments module coverage expanded across supported master-data edit pages (contacts, addresses, taxes, currencies, units, and price lists).
- API v1 auth moved to token-based access with Sanctum (`auth:sanctum`) including bearer token coverage tests.
- Superadmin operations reporting now supports saved filter presets and scheduled export delivery notifications.
- Notification governance analytics implemented on the platform dashboard (escalation outcomes, digest open coverage, noisy-event detection).
- Superadmin dashboard Sprint 1 modernization completed: chart-based delivery/governance visuals, consolidated export menu, and governance controls moved to dedicated `/platform/governance`.
- Superadmin dashboard Sprint 2 completed: drill-down KPI links and tabbed operations detail view (companies/invites/admin actions) with shared filter state.
- Superadmin dashboard Sprint 3 completed: per-admin dashboard personalization (saved layout order, widget visibility toggles, default operations tab, and default filter preset).
- Fixed platform dashboard operations tab state sync so invite/admin-action tabs correctly switch views when clicked.
- Operations detail tab clicks now switch client-side without Inertia reload when data is already present.
- Cleaned status-mix donut presentation to remove overlapping chart text and use a single readable legend/metric block.
- Moved superadmin dashboard personalization controls out of `/platform/dashboard` into settings at `/settings/dashboard-personalization` to reduce dashboard clutter.
- Added dedicated superadmin reports center at `/platform/reports` with centralized operations filters and report presets.
- Expanded platform report catalog coverage (admin actions, delivery trends, companies, platform admins, platform invites, notification events, performance snapshot).
- Switched platform report exports from CSV/JSON to PDF/XLSX only, including branded PDF templates and Excel sheet output.
- Removed operations filter/export controls from the platform dashboard and linked dashboard reporting actions to the new reports center.
- Updated platform dashboard chart styling with richer multi-color palettes for dark mode readability (delivery trend, status donut, noisy events).
- Fixed dashboard chart token rendering in dark mode by using native theme color variables (`--chart-*`) instead of invalid `hsl(...)` wrappers over OKLCH tokens.
- Moved the reports-center CTA to the platform dashboard header beside governance controls and removed the redundant reporting card.
- Updated global header controls so the notification bell opens a recent-notifications dropdown with a `See all` action, moved logout to a standalone header button, and reduced the sidebar user menu to settings-only.
- Refined header notification dropdown UX to compact card-style items with stronger spacing, timestamp-first metadata, and truncated one-line titles to prevent horizontal overflow/clutter.
- Added role-segregated route middleware enforcement for direct URL protection (`company.workspace`) so superadmins are redirected away from `/company/*` workspace routes while platform routes remain restricted to superadmins.
- Fixed notifications breadcrumbs to show `Platform > Notifications` for superadmins (and retain `Company > Notifications` for company users).
- Replaced company dashboard placeholder with a real KPI dashboard (`/company/dashboard`) including trend charts, invite status visualization, quick actions, master-data summary, and recent activity feed.
- Expanded company settings persistence/UI for tax periods, approval policy defaults, and numbering sequences (company-scoped settings keys + API exposure).
- Added final delivery planning doc at `docs/final-module-delivery-plan.md` covering expanded role architecture, module-by-module workflow states, rollout phases, and production-readiness gates (research-backed with Odoo/Dynamics references).
- Refactored route registration for maintainability by splitting `routes/web.php` into compact grouped files: `routes/company.php`, `routes/modules.php`, `routes/masterdata.php`, and `routes/platform.php` (loaded via `require` from `routes/web.php`).
- Moved authentication-related invite acceptance routes into dedicated `routes/auth.php` and wired it through `routes/web.php` for cleaner route organization.
- Refactored company module route organization into `routes/moduleroutes/*` (`sales.php`, `inventory.php`, `accounting.php`, `purchasing.php`, `approvals.php`, `reports.php`) and updated `routes/modules.php` to load module-specific route files.
- Moved domain module code from `app/Core/Sales` and `app/Core/Inventory` into `app/Modules/Sales` and `app/Modules/Inventory` with namespace/import rewiring to keep module boundaries explicit.
- Hardened logout/back-button behavior: authenticated web responses now send no-store cache headers, logout redirect also sends no-cache headers, and app bootstrap adds a BFCache `pageshow` reload guard on authenticated pages to prevent viewing stale protected screens after logout.
- Phase A role architecture foundation implemented: expanded module permission namespaces and seeded functional global roles (Operations Admin, Sales Manager/User, Inventory Manager, Warehouse Clerk, Purchasing Manager, Buyer, Finance Manager, Accountant, Approver, Auditor) while keeping owner/member compatibility.
- Added role-level data scope support (`own_records`, `team_records`, `company_records`, `read_all`) with helper methods and master-data policy enforcement for record-level access.
- Added approval authority profile persistence (`approval_authority_profiles`) and baseline segregation-of-duties service checks (separate requester/approver rules, approval amount limits, risk-level caps, accounting period-close permission gate).
- Updated company role and user management UI to surface role data scopes and module-role coverage more clearly.
- Added module placeholder route permission gating (`sales.*`, `inventory.*`, `purchasing.*`, `accounting.*`, `approvals.*`, `reports.*`) plus feature coverage for allowed/forbidden access.
- Phase B Sales MVP implemented: company Sales module now supports lead -> quote -> order workflow with lifecycle actions, company-scoped permissions/policies, approval-threshold enforcement, numbering sequences, and order-confirmation event emission for downstream inventory/accounting handoffs.
- Phase C Inventory MVP implemented: warehouses/locations CRUD, stock levels, receipts/deliveries/transfers workflow actions (reserve/complete/cancel), inventory dashboard KPIs, and automatic stock-move reservation on sales-order confirmation.
- Phase D Accounting foundations expanded beyond lite invoicing: chart of accounts, journals, general-ledger entries, double-entry posting/reversal on invoice and payment workflows, manual journal workflow, bank reconciliation batches, financial statements/trial balance/cash-flow reporting, accounting foundation pages (`/company/accounting/accounts`, `/journals`, `/ledger`, `/statements`, `/manual-journals`, `/bank-reconciliation`), demo-data ledger backfill, and end-to-end feature coverage.
- Accounting reconciliation controls expanded with explicit bank-batch unreconcile workflow (audit-tracked unreconcile actor/reason, payment/ledger stamp rollback, and reversible import lock release).
- Manual journals now support approval thresholds and supporting-document attachments (company setting for threshold override, approvals queue sync, posting gate until approval, and attachments panel on edit view).
- Bank reconciliation now supports persisted bank-statement CSV import with line-level matching, preview/review, and batch creation from imported statement lines instead of manual payment picking.
- Bank statement import now supports OFX and CAMT XML parsing in addition to CSV.
- Bank reconciliation review now supports manual rematch and clear actions for unmatched/ambiguous statement lines, with downloadable CSV/OFX/CAMT sample files from the import screen.
- Phase E Purchasing MVP implemented: RFQ + purchase order schema, dashboard + RFQ/PO workflows, approval and placement controls, receipt capture with partial/full handling, and automatic vendor-bill draft handoff to accounting on receipts.
- Phase F Approvals module implemented: `approval_requests` + `approval_steps` persistence, cross-module queue sync (sales quote/order + purchase order), authority-aware approve/reject actions, and approval SLA metrics at `/company/approvals`.
- Phase F Reports module implemented: company reports center at `/company/reports` with operational/financial catalog coverage, shared filters/presets, PDF/XLSX exports, and financial statement exports (profit and loss, balance sheet, trial balance, cash flow summary).
- Company scheduled report delivery implemented (`company:reports:deliver-scheduled`) with per-company schedule settings, preset/report selection, and in-app delivery notifications.
- Role-specific company dashboards implemented on `/company/dashboard` for Sales, Inventory, and Finance roles, with tailored KPI cards, role-focused quick actions, and module-priority focus signals.
- Scheduled platform operations report delivery expanded with multi-channel dispatch (`in_app`, `email` with attachments, `webhook`, `Slack`) and recipient targeting (all or selected platform admins plus additional external emails).
- Notification governance analytics drill-downs implemented with daily time-series trends, source-level segmentation, and configurable noisy-event thresholds.
- Added standalone demo dataset seeder `Database\\Seeders\\DemoCompanyWorkflowSeeder` for full-company walkthrough data (company + one user per role, invites, 20 sales workflows, 20 purchasing workflows, inventory/accounting/approvals links, notifications, and audit activity).
- CI test workflow now provisions PostgreSQL with `pdo_pgsql`/`pgsql` extensions and runs the suite against the project's PostgreSQL test configuration.
- Projects/Services Phase 1 foundation started: schema and Eloquent models added for `projects`, `project_members`, `project_stages`, `project_tasks`, `project_timesheets`, `project_milestones`, and `project_billables`.
- Projects/Services authorization foundation added: `projects.*` permissions seeded, `project_manager` / `project_user` functional roles introduced, project policies registered, and policy coverage added for project membership-aware access and finance billing access.
- Projects/Services initial workspace implemented: module dashboard, sidebar/module route wiring, workspace list with filters, project CRUD, project detail summary, task CRUD, automatic default-stage provisioning, project-member sync for managers/assignees, and end-to-end feature coverage.
- Projects/Services timesheet workflow implemented: project-level timesheet create/edit/delete, own-vs-team rate handling, submission/approval/rejection actions, task-hour rollups, and role-aware UI/actions from the project workspace.
- Projects/Services milestone workflow implemented: project milestone create/edit/delete, sequence management, review/approval-ready status tracking, milestone billing-readiness stamping, and workspace visibility/tests.
- Projects/Services billable-generation foundation implemented: approved timesheets and approved milestones now sync idempotently into `project_billables`, non-qualifying source changes cancel existing billables instead of duplicating them, and project billable totals exclude cancelled items.
- Projects/Services billing queue implemented: dedicated `/company/projects/billables` review page with project/customer/status/approval/type filters, ready-to-invoice and uninvoiced summary metrics, accessible project/customer filter options, and queue entry links from the Projects dashboard/workspace/detail pages.
- Projects/Services billable approval workflow implemented: company approval-threshold settings now drive whether generated billables require approval, project/finance reviewers can approve, reject, or cancel billables from the queue with decision reasons, and approval-controlled project billables now sync into the shared Approvals module for approver-role handling.
- Projects/Services invoice draft handoff implemented: selected eligible project billables can now be grouped by project or customer into draft customer invoices, with queue-based selection UI, accounting draft creation through the shared invoice workflow service, billable-to-invoice linkage, and source timesheet/milestone invoice-state stamping.
- Projects/Services project detail billing integration implemented: project detail pages now surface billing-status summary cards, project-scoped billable rows with invoice eligibility selection, direct invoice-draft creation from the project page, and linked accounting invoice visibility without leaving the project workspace.

## Not Yet Implemented

- Projects/Services profitability dashboard signals and recurring billing flows.

## Deferred / Out of Scope

- Ownership-mode runtime switching (`APP_OWNERSHIP_MODE`) is intentionally deferred and excluded from the current delivery scope.

## Functional Status (Audit)

### Working (implemented end-to-end)

- Authentication flows (login, password reset, verification, two-factor settings/challenge).
- Invite acceptance and invite-driven user provisioning (`/invites/{token}`).
- Multi-company context resolution, company switching, and inactive-company safeguards.
- Platform superadmin area: dashboard, companies (list/create/show/update), platform admins, platform invites.
- Platform dashboard operations reporting now supports filtered PDF/XLSX exports for admin actions and invite delivery trends.
- Platform dashboard operations reporting now supports saved presets and scheduled export delivery policies.
- Platform reports center now centralizes operations filters and exports in PDF/XLSX for core platform datasets.
- Platform governance settings are now managed from `/platform/governance` (delivery schedule + notification governance controls), while the dashboard stays monitoring-focused.
- Platform dashboard now supports invite-delivery drill-down filtering and applies user-level default filter presets when no explicit filter query is provided.
- Platform dashboard personalization preferences persist per superadmin via settings (`platform.dashboard.preferences`) and are managed from `/settings/dashboard-personalization`.
- Header actions now include in-place notification preview dropdowns and a dedicated logout button before the company switcher.
- Direct URL route protection now enforces workspace separation: superadmins cannot open company workspace routes, and company users remain blocked from platform routes.
- Company owner dashboard now delivers real KPIs, trend charts, quick actions, and recent activity insights at `/company/dashboard`.
- Company dashboard now auto-switches to role-focused variants for Sales, Inventory, and Finance users, with module-specific KPIs and quick actions while preserving owner baseline behavior.
- Company workspace management pages: settings, users (role updates), roles, company invites.
- Phase A role architecture baseline is live: functional module roles are seeded with module permission bundles and role-level data scopes.
- Master-data policies now enforce record-level data scope boundaries for non-owner company users.
- Approval authority profile model/service foundation and SoD checks are available for upcoming module approval flows.
- Sales module is now live at `/company/sales` with leads/quotes/orders CRUD, quote+order approvals, and conversion/confirmation workflow actions.
- Inventory module is now live at `/company/inventory` with warehouse/location management, stock-level visibility, and stock-move lifecycle controls.
- Accounting module is now live at `/company/accounting` with invoices/payments CRUD, posting, invoice reconciliation, bank reconciliation batches, unreconcile controls, persisted bank-statement CSV/OFX/CAMT imports, manual rematch controls for statement exceptions, reversal safeguards, manual journals, manual-journal approval thresholds, supporting-document attachments, chart of accounts, journals, general-ledger visibility, financial statements, and sales/inventory handoff support.
- Purchasing module is now live at `/company/purchasing` with RFQ/PO CRUD, approval and placement lifecycle actions, receipt capture, and vendor bill handoff into accounting.
- Approvals module is now live at `/company/approvals` with unified queue filtering, authority-checked approve/reject actions, and cross-module request tracking.
- Reports module is now live at `/company/reports` with operational + financial report cards, preset management, PDF/XLSX exports, and financial statement exports.
- Company report scheduling is live with per-company delivery policy and recurring notification dispatch via `company:reports:deliver-scheduled`.
- Company settings now include tax cadence defaults, approval-policy defaults, and numbering sequence controls beyond profile/localization fields.
- Master data CRUD for partners, contacts, addresses, products, taxes, currencies, units, and price lists.
- Governance audit logs: listing, filtering, export (CSV/JSON), and delete actions.
- Permission-based UI and route/controller authorization for master data and governance.
- Core master-data foreign-key validation now enforces active-company ownership for related records.
- Company settings now persist operational defaults (`fiscal_year_start`, `locale`, `date_format`, `number_format`, audit retention days) via the new `settings` service/table.
- In-app notifications center is live at `/core/notifications` with unread counters, mark-read, mark-all-read, and delete actions.
- Notifications now emit for company settings updates, role changes, invite acceptance, company status changes, and invite delivery failures.
- Notification governance is now configurable from platform dashboard (minimum severity, escalation behavior, digest policy) and enforced by the notification service.
- Notification governance analytics are now available from platform dashboard with escalation acknowledgement rates, digest open rates, and top noisy events.
- Platform governance analytics now include drill-downs (time-series trend lines, per-source segmentation) and threshold-based noisy-event filtering configured via governance settings.
- Attachment upload/download/delete flows are live via `/core/attachments`, with attachments panels across partner, product, contact, address, tax, currency, unit, and price-list edit pages.
- Audit retention command now prunes with company-specific settings fallback and is scheduled daily.
- Scheduled platform digest dispatch is available via `platform:notifications:send-digest` and wired into the scheduler.
- Scheduled operations report delivery is available via `platform:operations-reports:deliver-scheduled` and wired into the scheduler.
- Scheduled operations report delivery now points to reports-center export links and PDF/XLSX formats.
- Scheduled operations report delivery now supports targeted recipients and channel fan-out (in-app/email/webhook/Slack) with PDF/XLSX attachments for email dispatch.
- API v1 scaffolding is live at `/api/v1` for health, partners, products, and settings, protected by Sanctum token auth.
- Full demo-company seed data is now available via `php artisan db:seed --class=Database\\Seeders\\DemoCompanyWorkflowSeeder` for presentation and end-to-end workflow demos, including accounting ledger/account/journal setup and financial-statement-ready postings.
- Company settings and API settings payloads now expose a dedicated manual-journal approval threshold override alongside the shared approval defaults.
- Projects module is now live at `/company/projects` with a dashboard, searchable workspace list, project detail pages, project/task CRUD, timesheet approvals, and milestone tracking for delivery teams with role-aware access.

### Present but placeholder-only

- Projects/Services now covers project/task/timesheet/milestone execution plus automatic billable generation, billables review, approval workflow integration, draft invoice handoff into Accounting, and project detail billing visibility, but portfolio profitability signals and recurring billing flows are still pending.

### Test run result (2026-03-22)

- Command executed: `php artisan test`.
- Test runtime uses PostgreSQL test DB (`phpunit.xml` sets `DB_CONNECTION=pgsql`, `DB_DATABASE=port_101_test`).
- Local verification status: suite executes on PostgreSQL and is fully passing.
- Result summary after latest implementation: `186` passed, `0` failed.

## Suggestions

- Add a dedicated `testing` DB profile and CI preflight check that fails fast with a clear message when the required PDO driver is missing.
- Implement shared request helpers/rules for company-scoped `exists` validations to avoid cross-company reference leaks.
- Add feature tests for platform/company management flows that are currently lightly covered: company switch, inactive-company redirects, role updates, and platform company ownership changes.
- Introduce a reusable CRUD abstraction (or shared table/form components) for master-data modules to reduce repeated controller/page logic and keep behavior consistent.
- Extend retention operations with archive mode and telemetry (number pruned per company/day) before hard delete.
- Add notification preferences (per-category opt-in, mute windows, digest mode) to prevent alert fatigue as event volume grows.
- Add attachment hardening (virus scanning queue, MIME allowlists by module, and pre-signed URL support for cloud storage).
- Formalize API versioning policy (deprecation headers + change log) before exposing `/api/v1` to third parties.
