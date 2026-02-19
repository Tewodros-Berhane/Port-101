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

## Not Yet Implemented

- Ownership-mode config wiring (`APP_OWNERSHIP_MODE`) described in docs is not yet implemented in code.
- Token-based API auth for external integrations (current `/api/v1` scaffolding still uses app session auth middleware).
- Attachments integration coverage for all supported master-data pages (currently wired on partner and product edit flows).
- Company settings expansion beyond current defaults (tax periods, approval policies, numbering sequences).
- Role dashboards with KPI cards and quick actions (Owner, Sales, Inventory, Finance).
- Sales workflow slice: leads -> quotes -> sales orders (list/create/edit).
- Inventory slice: warehouses/locations, stock levels, receipts/deliveries.
- Accounting lite: invoices and payments flow.
- Purchasing slice: vendors, RFQs, purchase orders, receipts.
- Approvals queue implementation.
- Reports implementation (financial + operational views).

## Functional Status (Audit)

### Working (implemented end-to-end)

- Authentication flows (login, password reset, verification, two-factor settings/challenge).
- Invite acceptance and invite-driven user provisioning (`/invites/{token}`).
- Multi-company context resolution, company switching, and inactive-company safeguards.
- Platform superadmin area: dashboard, companies (list/create/show/update), platform admins, platform invites.
- Platform dashboard operations reporting now supports filtered CSV/JSON exports for admin actions and invite delivery trends.
- Company workspace management pages: settings, users (role updates), roles, company invites.
- Master data CRUD for partners, contacts, addresses, products, taxes, currencies, units, and price lists.
- Governance audit logs: listing, filtering, export (CSV/JSON), and delete actions.
- Permission-based UI and route/controller authorization for master data and governance.
- Core master-data foreign-key validation now enforces active-company ownership for related records.
- Company settings now persist operational defaults (`fiscal_year_start`, `locale`, `date_format`, `number_format`, audit retention days) via the new `settings` service/table.
- In-app notifications center is live at `/core/notifications` with unread counters, mark-read, mark-all-read, and delete actions.
- Notifications now emit for company settings updates, role changes, invite acceptance, company status changes, and invite delivery failures.
- Notification governance is now configurable from platform dashboard (minimum severity, escalation behavior, digest policy) and enforced by the notification service.
- Attachment upload/download/delete flows are live via `/core/attachments`, including partner/product edit-page attachments panels.
- Audit retention command now prunes with company-specific settings fallback and is scheduled daily.
- Scheduled platform digest dispatch is available via `platform:notifications:send-digest` and wired into the scheduler.
- API v1 scaffolding is live at `/api/v1` for health, partners, products, and settings.

### Present but placeholder-only

- Company dashboard (`/company/dashboard`) is still a placeholder layout.
- Company modules are placeholders only: Sales, Inventory, Purchasing, Accounting, Approvals, Reports.

### Test run result (2026-02-19)

- Command executed: `php artisan test` (requested with long timeout).
- Test runtime now uses PostgreSQL test DB (`phpunit.xml` updated to `DB_CONNECTION=pgsql`, `DB_DATABASE=port_101_test`).
- Current status: suite executes on PostgreSQL and is fully passing.
- Result summary after latest implementation: `93` passed, `0` failed.

## Next Steps (Priority Order)

1. Expand attachments module coverage:
   - Add attachment panels to contacts, addresses, taxes, currencies, units, and price lists.
2. Move API v1 auth from session middleware to integration-ready token auth (for example, Sanctum or equivalent).
3. Implement company dashboards with real KPIs and quick actions.
4. Build Phase 1 module slices:
   - Sales (lead -> quote -> order), Inventory (stock/receipts/deliveries), Accounting lite (invoices/payments).
5. Build Phase 2 purchasing slice:
   - Vendors, RFQs, POs, receipts, and vendor bill handoff.
6. Implement approvals queue and reporting views.

## Next Steps (Superadmin)

- Saved operations-report filter presets and scheduled export delivery.
- Notification governance analytics (escalation outcomes, digest send/open coverage, noisy-event detection).

## Next Steps (Owner + Modules)

- Company settings expansion beyond current defaults (tax periods, approval policies, numbering sequences).
- Role dashboards with KPI cards and quick actions (Owner, Sales, Inventory, Finance).
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
