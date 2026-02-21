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

## Not Yet Implemented

- Role dashboards beyond owner baseline (Sales, Inventory, Finance specific KPI/quick-action variants).
- Sales workflow slice: leads -> quotes -> sales orders (list/create/edit).
- Inventory slice: warehouses/locations, stock levels, receipts/deliveries.
- Accounting lite: invoices and payments flow.
- Purchasing slice: vendors, RFQs, purchase orders, receipts.
- Approvals queue implementation.
- Reports implementation (financial + operational views).

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
- Company workspace management pages: settings, users (role updates), roles, company invites.
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
- Attachment upload/download/delete flows are live via `/core/attachments`, with attachments panels across partner, product, contact, address, tax, currency, unit, and price-list edit pages.
- Audit retention command now prunes with company-specific settings fallback and is scheduled daily.
- Scheduled platform digest dispatch is available via `platform:notifications:send-digest` and wired into the scheduler.
- Scheduled operations report delivery is available via `platform:operations-reports:deliver-scheduled` and wired into the scheduler.
- Scheduled operations report delivery now points to reports-center export links and PDF/XLSX formats.
- API v1 scaffolding is live at `/api/v1` for health, partners, products, and settings, protected by Sanctum token auth.

### Present but placeholder-only

- Company modules are placeholders only: Sales, Inventory, Purchasing, Accounting, Approvals, Reports.

### Test run result (2026-02-20)

- Command executed: `php artisan test` (requested with long timeout).
- Test runtime now uses PostgreSQL test DB (`phpunit.xml` updated to `DB_CONNECTION=pgsql`, `DB_DATABASE=port_101_test`).
- Current status: suite executes on PostgreSQL and is fully passing.
- Result summary after latest implementation: `103` passed, `0` failed.

## Next Steps (Priority Order)

1. Build Phase 1 module slices:
    - Sales (lead -> quote -> order), Inventory (stock/receipts/deliveries), Accounting lite (invoices/payments).
2. Build Phase 2 purchasing slice:
    - Vendors, RFQs, POs, receipts, and vendor bill handoff.
3. Implement approvals queue and reporting views.

## Next Steps (Superadmin)

- Export delivery channel expansion (email attachments/webhooks/Slack) and recipient targeting beyond all superadmins.
- Governance analytics drill-downs (time-series trends, per-source segmentation, configurable noisy-event thresholds).

## Next Steps (Owner + Modules)

- Role dashboards beyond owner baseline (Sales, Inventory, Finance specific KPI/quick-action variants).
- Sales workflow slice: leads -> quotes -> sales orders (list/create/edit).
- Inventory slice: warehouses/locations, stock levels, receipts/deliveries.
- Accounting lite: invoices and payments flow.
- Purchasing slice: vendors, RFQs, purchase orders, receipts.
- Approvals queue implementation.
- Reports implementation (financial + operational views).

## Suggestions

- Add a dedicated `testing` DB profile and CI preflight check that fails fast with a clear message when the required PDO driver is missing.
- Implement shared request helpers/rules for company-scoped `exists` validations to avoid cross-company reference leaks.
- Add feature tests for platform/company management flows that are currently lightly covered: company switch, inactive-company redirects, role updates, and platform company ownership changes.
- Introduce a reusable CRUD abstraction (or shared table/form components) for master-data modules to reduce repeated controller/page logic and keep behavior consistent.
- Wire the documented ownership mode config into runtime behavior so platform-only/company-only behaviors are explicit and testable.
- Extend retention operations with archive mode and telemetry (number pruned per company/day) before hard delete.
- Add notification preferences (per-category opt-in, mute windows, digest mode) to prevent alert fatigue as event volume grows.
- Add attachment hardening (virus scanning queue, MIME allowlists by module, and pre-signed URL support for cloud storage).
- Formalize API versioning policy (deprecation headers + change log) before exposing `/api/v1` to third parties.
