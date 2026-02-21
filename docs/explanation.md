# ERP Web App Flow and Navigation (User Perspectives)

## Purpose

This document explains how users move through the ERP, what they see, and how the main workflows are navigated. It is written in plain terms to guide planning and UX decisions.

## Common Navigation Concepts

- App shell: persistent sidebar or top navigation with modules and shortcuts.
- Company context: users operate inside one company at a time and can switch company if they belong to more than one.
- Role-based menus: what appears in the navigation depends on permissions.
- Records: most screens follow the pattern list -> detail -> create/edit.
- Global search: quick jump to customers, products, invoices, orders.
- Notifications: approvals and system events are surfaced in-app and by email.
- Settings: user settings (profile, password) and company settings (taxes, currencies, users).

## High-Level Flow (All Users)

1. Login
2. Choose or confirm company context
3. Land on role-specific dashboard
4. Navigate to a module (Sales, Inventory, Accounting, etc.)
5. Work with records in that module
6. Review notifications and approvals
7. Logout or switch company

## User Perspectives

### 1) Owner / Company Admin

Primary goal: configure the company and monitor all operations.

Typical navigation:

- Dashboard: overall KPIs (cash, receivables, sales, stock)
- Settings -> Company: company profile, fiscal year, taxes, currencies
- Settings -> Users/Roles: invite users, assign roles, manage permissions
- Master Data: customers, vendors, products, price lists
- Approvals: review large orders or payments

Key flows:

- Create company settings before any transactions
- Add users and assign roles
- Configure products and taxes

### Company Owner Invite and User Creation Flow (Current Implementation)

This is how user onboarding currently works in the code today.

#### Who can create company users?

- Superadmin can bootstrap the company and its first owner from platform screens.
- After that, company owners (or any user with `core.users.manage`) can invite users directly from the company workspace.
- Public self-registration is disabled; onboarding is invite-only.

#### Where company owners send invites

- Company owner opens `Company -> Users` then `Manage invites` (goes to `/core/invites`).
- Click `New invite` (`/core/invites/create`).
- Enter:
  - email
  - optional name
  - role (`company_member` or `company_owner`)
  - optional expiry date
- Submit creates an invite record and queues email delivery.

#### What happens when the invite is accepted

- Recipient opens `/invites/{token}` and sets password.
- If no user exists for that email, user is created automatically.
- If user already exists, that existing account is used.
- System marks email verified, attaches user to the target company, and assigns role:
  - `company_owner` -> owner membership (`is_owner = true`)
  - `company_member` -> member membership (`is_owner = false`)
- User is signed in and redirected to dashboard.

#### Is superadmin required for every new employee?

- No. Superadmin is mainly for platform-level setup/governance.
- Day-to-day hiring/onboarding inside a company is handled by company owner via company invites.

#### If the company needs a "Sales Manager" role

- Current seeded roles are mainly `Owner` and `Member` by default.
- Invite flow can onboard the person as `company_member`, then owner can update their role from `/company/users` if that role exists in the roles table.
- Dedicated business roles (Sales Manager, Inventory Manager, Finance Manager) with tailored permissions are still part of the remaining role/module roadmap.

### 2) Finance / Accounting User

Primary goal: invoices, payments, and financial statements.

Typical navigation:

- Accounting -> Dashboard: receivables/payables summary
- Invoices -> List: view draft/posted invoices
- Payments -> Reconciliation: match payments to invoices
- Reports -> Financials: P&L, balance sheet, cash flow

Key flows:

- Sales order -> Invoice draft -> Post invoice
- Record payment -> Reconcile -> Update ledger

### 3) Sales User

Primary goal: convert leads to orders and invoices.

Typical navigation:

- Sales -> Pipeline: leads and opportunities
- Sales -> Quotes: create and send quotes
- Sales -> Orders: confirm orders and trigger fulfillment
- Customers -> List: review customer history

Key flows:

- Lead -> Quote -> Sales Order
- Sales Order -> Invoice (hand-off to accounting)

### 4) Inventory / Warehouse User

Primary goal: maintain stock accuracy and fulfill orders.

Typical navigation:

- Inventory -> Stock Levels: view stock by warehouse
- Inventory -> Receipts: receive incoming stock
- Inventory -> Transfers: move stock between locations
- Inventory -> Deliveries: fulfill sales orders

Key flows:

- Receive stock -> Update on-hand
- Sales order -> Reserve stock -> Deliver to customer

### 5) Purchasing User

Primary goal: source goods and manage vendors.

Typical navigation:

- Purchasing -> Vendors: manage supplier data
- Purchasing -> RFQs: request pricing
- Purchasing -> Purchase Orders: confirm orders
- Inventory -> Receipts: receive stock from vendors

Key flows:

- Reorder need -> RFQ -> Purchase Order -> Receipt
- Purchase Order -> Vendor bill (hand-off to accounting)

### 6) Manager / Approver

Primary goal: approve key transactions and review performance.

Typical navigation:

- Dashboard: KPI summary
- Approvals: pending approvals for orders, discounts, or payments
- Reports: team performance and trends

Key flows:

- Review approval requests -> Approve/Reject -> Notification to requester

### 7) Read-Only / Auditor

Primary goal: visibility without making changes.

Typical navigation:

- Reports: financial and operational views
- Records: view-only access to orders, invoices, and ledger

Key flows:

- Filter and export reports for audit/review

## How Modules Feel in the UI

- Sidebar groups by business area: Sales, Purchases, Inventory, Accounting, Settings
- Each module has consistent pages: list, detail, create/edit
- Breadcrumbs show where you are in the system

## Example End-to-End Flow (Quote to Cash)

1. Sales creates a quote
2. Quote is approved (if required)
3. Quote becomes a sales order
4. Inventory reserves stock
5. Warehouse delivers goods
6. Accounting posts invoice
7. Finance records payment and reconciles

## Example End-to-End Flow (Procure to Pay)

1. Purchasing creates RFQ
2. Vendor responds; PO is created
3. Inventory receives goods
4. Vendor bill is recorded
5. Payment is approved and completed

## Planning Notes

- Start with a simple navigation structure that can grow with new modules.
- Keep role-based dashboards focused on the most common tasks.
- Always expose company context and user role clearly in the UI.

## Ownership Model Options

The system can be used in two distinct ways, and the choice affects whether a super admin role is needed.

### 1) Platform-Owned (SaaS)

- The platform operator owns the app and provisions companies.
- Super admins exist for platform support, compliance, and cross-company oversight.
- Company owners still manage their own company data, users, and roles.

### 2) Company-Owned (Self-Managed)

- Each company owns its own instance and manages everything internally.
- Super admin is unnecessary and can be disabled.
- The first user creates the company and becomes the owner.

## Choosing Between the Two

Use a deployment-level setting to decide the mode for an instance.

- `APP_OWNERSHIP_MODE=platform`
    - Enables super-admin-only screens (company registry, support access).
    - Company creation can be restricted to platform admins.
- `APP_OWNERSHIP_MODE=company`
    - Disables/hides platform admin UI and super admin flows.
    - Company creation is self-serve; first user becomes owner.

This keeps both models available without changing core workflows. The same permissions model applies, but super admin capabilities are only active in platform mode.

## Audit Log Module and Event Hooks

The audit log module is a system record of who did what, to which record, and when. It is designed for traceability, troubleshooting, and compliance without changing the core workflows.

What it stores per entry:

- Company and actor: `company_id` plus the user who performed the action (when available).
- Target record: `auditable_type` and `auditable_id` so any model can be tracked.
- Action: created, updated, deleted (and restored when applicable).
- Changes: a before/after snapshot of the fields that changed (system fields are excluded).
- Context: request metadata like IP address and user agent when available.

Event hooks are the automatic triggers that create audit entries. They listen to model lifecycle events (created, updated, deleted, restored) and write the audit record consistently so we do not have to remember to log each controller action manually.

Current scope:

- Master data models: partners, contacts, addresses, products, taxes, currencies, units, and price lists.
