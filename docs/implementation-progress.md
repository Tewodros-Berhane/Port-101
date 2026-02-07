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

## Not Yet Implemented

- Audit log retention rules.
- Company settings expansion (fiscal year defaults, locale controls).
- Role dashboards with KPI cards and quick actions (Owner, Sales, Inventory, Finance).
- Sales workflow slice: leads -> quotes -> sales orders (list/create/edit).
- Inventory slice: warehouses/locations, stock levels, receipts/deliveries.
- Accounting lite: invoices and payments flow.
- Purchasing slice: vendors, RFQs, purchase orders, receipts.
- Approvals queue implementation.
- Reports implementation (financial + operational views).

## Next Steps (Superadmin)

- Company suspension lifecycle UX polish (dedicated inactive-company messaging flows).
- Platform operations reporting improvements (delivery-failure trends, admin-action filtering).

## Next Steps (Owner + Modules)

- Company settings expansion (fiscal year defaults, locale controls).
- Role dashboards with KPI cards and quick actions (Owner, Sales, Inventory, Finance).
- Sales workflow slice: leads -> quotes -> sales orders (list/create/edit).
- Inventory slice: warehouses/locations, stock levels, receipts/deliveries.
- Accounting lite: invoices and payments flow.
- Purchasing slice: vendors, RFQs, purchase orders, receipts.
- Approvals queue implementation.
- Reports implementation (financial + operational views).
