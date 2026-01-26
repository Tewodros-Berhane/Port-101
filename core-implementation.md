# Core/Platform Implementation Plan (Laravel)

## Goal

Deliver a production-grade core platform that every module builds on. This includes authentication, multi-company scoping, RBAC, master data, auditability, settings, attachments, notifications, and foundational APIs.

## Guiding Principles

- Single database with company scoping (`company_id` on all business entities)
- Modular monolith with shared kernel in `app/Core`
- Event-driven integration between core and modules
- Opinionated defaults for faster onboarding, with room for configuration

## Database Connectivity (Neon)

- Use the Neon direct endpoint for both app traffic and migrations for now.
- Reason: the Neon pooler does not support DDL inside transactions; Laravel migrations run in transactions.
- If we later switch to pooling, use a separate direct connection for migrations and run them with `--database=pgsql_direct`.

## Deliverables Overview

1. Core data model and migrations
2. Auth and identity
3. RBAC and permission framework
4. Multi-company and tenant scoping
5. Master data management
6. Audit logging and activity timeline
7. Settings and configuration
8. File attachments and media
9. Notifications and email
10. API scaffolding and versioning
11. Inertia + React UI foundation
12. Seeds, fixtures, and test scaffolding

## 1) Core Data Model

### Primary tables

- companies
- company_users (pivot for user membership and role)
- users
- roles
- permissions
- role_permissions (pivot)
- partner_types (customer, vendor, both)
- partners
- contacts
- addresses
- products
- uoms (units of measure)
- currencies
- taxes
- price_lists (optional for phase 1)
- audit_logs
- activity_logs
- attachments
- settings

### Mandatory columns (baseline)

- `company_id` on all business entities and master data
- `created_by`, `updated_by` where user context matters
- `deleted_at` for soft deletes
- `uuid` or `ulid` for external references (optional but recommended)

### Indexing strategy

- Composite indexes on (`company_id`, `id`)
- Unique constraints with company scope (e.g., product SKU)
- Time-based indexes for audit/activity logs

## 2) Auth and Identity

### Implementation

- Laravel Breeze (Inertia + React) for auth scaffolding
- Use email + password (SAML/OAuth later)
- Require email verification
- Password reset with throttling

### Sessions and security

- Session driver: Redis
- Enforce strong password policy
- Configure login throttling and account lockouts

## 3) RBAC and Permissions

### Models

- Role (name, slug, scope)
- Permission (name, slug, scope)
- Many-to-many mapping

### Policies and Gates

- Use Laravel Policies per model
- `CompanyScoped` policy trait for shared logic
- Gates for module-specific permissions

### Admin UI

- Role management
- Permission assignment
- User membership per company

## 4) Multi-Company Scoping

### Strategy

- Use `company_id` on all records
- `CompanyScoped` global query scope on base models
- Set company context on request via middleware

### Middleware

- `ResolveCompanyContext` from subdomain or header
- `EnsureCompanyMembership` for access

### Super Admin

- Optional global admin bypass for internal users

## 5) Master Data Management

### Partners

- Partner types: customer, vendor, both
- Contacts and addresses
- Partner status (active/inactive)

### Products

- Product types: stockable, service
- UoM, SKU, barcode
- Taxes, default accounts

### Taxes and Currencies

- Multi-rate tax support
- Multi-currency handling with base currency per company

### UI Requirements

- List, detail, create, edit for each master entity
- Bulk import via CSV

## 6) Audit Logging and Activity Timeline

### Audit Logs

- Capture create/update/delete
- Store old and new values (JSON)
- Include user, IP, device

### Activity Timeline

- Business events: order created, invoice posted
- Streamed per entity and per company

## 7) Settings and Configuration

### Settings

- Company-level and user-level
- Key-value model with JSON support

### Example settings

- Default currency
- Fiscal year start
- Tax defaults
- Date/number formats

## 8) Attachments and Media

### Storage

- Local for dev, S3-compatible for prod
- Store metadata (size, mime, checksum)

### Access

- Permission-based file access
- Signed URLs for download

## 9) Notifications and Email

### Channels

- Email, in-app notifications
- Future: SMS, Slack

### Templates

- Laravel Notifications and Mailables
- Centralized template management

## 10) API Scaffolding

### API Design

- Versioned `/api/v1` for external integrations
- Resource-based controllers with FormRequest validation
- JSON:API-inspired responses
- Internal UI data served via Inertia page props

### Auth

- Laravel Sanctum for API tokens
- Token scopes tied to permissions

## 11) Inertia + React UI Foundation

### Frontend stack

- Inertia.js + React + Vite + Tailwind
- React pages live in `resources/js/pages`
- Shared UI in `resources/js/components`
- App shell and nav in `resources/js/layouts`
- Hooks and utilities in `resources/js/hooks` and `resources/js/utils`
- Type definitions in `resources/js/types`

### Inertia conventions

- Controllers return `Inertia::render('Module/Page', props)`
- Page components map to `resources/js/pages/Module/Page.tsx`
- Shared props via `app/Http/Middleware/HandleInertiaRequests.php` include auth user, company context, permissions, flash messages, validation errors, locale, date/number formats, feature flags
- Use `@inertiajs/react` `Link`, `Head`, `useForm`, `usePage`
- Server-side pagination and filters; query params preserved with Inertia

### Layout and navigation

- Role-based menu visibility driven by permissions
- Global layout wraps all pages and reads shared props
- Standardized list/detail/edit layouts for master data

### Auth UI

- Inertia React auth pages under `resources/js/pages/Auth`
- Email verification and password reset flows

## 12) Seeds and Fixtures

### Demo data

- Default roles and permissions
- Sample company, users
- Sample partners and products

## 13) Testing Strategy

### Test types

- Unit tests for services and policies
- Feature tests for auth, RBAC, scoping
- API tests for core endpoints

### Tooling

- PHPUnit, Laravel test helpers
- Factories for master data

## Implementation Sequence (Step-by-Step)

### Phase A: Foundation

1. Create base models and migrations
2. Build RBAC models and policies
3. Implement company scoping middleware
4. Add Inertia + React layout and auth flows

### Phase B: Master Data

5. Build Partners, Products, Taxes, Currencies
6. Add CRUD pages and API endpoints

### Phase C: Audit and Settings

7. Add audit logging and activity timeline
8. Implement settings system

### Phase D: Integrations

9. Add attachments
10. Add notifications
11. Finalize API versioning

## Acceptance Criteria

- All core tables migrated and seeded
- Users can sign in and manage company data
- Roles and permissions enforce access control
- All queries scoped by `company_id`
- Audit logs are created for create/update/delete
- Core CRUD operations available via UI and API

## Risks and Mitigations

- Complexity of permissions: start with role presets
- Scope leaks: enforce global scopes and tests
- Performance: index by company and use eager loading

## Open Questions

- Subdomain vs company selector for context
- Default roles and permission sets
- Internationalization priorities
