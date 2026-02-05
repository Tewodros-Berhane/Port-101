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
- Platform company registry list, create flow, and detail page.

## Not Yet Implemented

- Audit log retention rules.

## TODO (Platform Mode)

- Enforce invite-only internal registration (disable self-serve signups for staff roles).
- Add platform admin company registry + create company flow with owner assignment.
- Prevent non-platform admins from creating companies.
- Add invite flow for company owners/admins to create users inside their company.

## Next Steps (Superadmin)

- Platform admin user management (platform admins/support accounts).
- Registration/invite management (issue invites, disable public registration).

## Next Steps (Owner + Modules)

- Company settings pages (profile, fiscal year, currency defaults).
- Users & roles management pages with RBAC assignment UI.
- Role dashboards with KPI cards and quick actions (Owner, Sales, Inventory, Finance).
- Sales workflow slice: leads -> quotes -> sales orders (list/create/edit).
- Inventory slice: warehouses/locations, stock levels, receipts/deliveries.
- Accounting lite: invoices and payments flow.
- Purchasing slice: vendors, RFQs, purchase orders, receipts.
- Approvals queue + notifications entry points.
- Reports entry points for financial and operational views.
