# Core Implementation Plan (Based on Current Codebase)

## Current State (Implemented)

- Laravel 12 + Inertia + React starter scaffold is in place (`composer.json`, `resources/js/app.tsx`).
- Fortify authentication flows are wired to Inertia pages (`app/Providers/FortifyServiceProvider.php`, `resources/js/pages/auth/*`).
- User settings flows exist (profile, password, appearance, two-factor) (`routes/settings.php`, `app/Http/Controllers/Settings/*`, `resources/js/pages/settings/*`).
- Two-factor auth is enabled with user table columns and UI (`database/migrations/2025_08_26_100418_add_two_factor_columns_to_users_table.php`).
- Basic DB tables are present: users, sessions, password_reset_tokens, cache, jobs.
- Inertia shared props include app name, auth user, and sidebar state (`app/Http/Middleware/HandleInertiaRequests.php`).
- No `app/Core` or module structure exists yet (glob search shows none).
- No API routes file exists (`routes/api.php` missing).

## Core Gaps (Not Implemented Yet)

- Company model, multi-company scoping, and tenant context resolution.
- RBAC (roles/permissions) and policy enforcement.
- Master data models/tables (partners, products, taxes, currencies, UoM).
- Audit logging and activity timeline.
- Settings storage (company/user settings table + service).
- Attachments/media handling.
- Notifications (in-app + email templates beyond auth).
- External API structure with versioned endpoints.
- Seeders for default roles, company, and master data.

## Implementation Plan by Phase

### Phase 1: Core Foundation (Fill Missing Platform Layer)

Goal: establish the shared kernel so all future modules are company-scoped, permissioned, and stable.

1. Create Core folder structure
    - Add `app/Core/Company`, `app/Core/RBAC`, `app/Core/MasterData`, `app/Core/Support`.
    - Add base traits/services (CompanyScoped, CompanyContext, Permission checks).

2. Add core migrations (single database)
    - `database/migrations/*_create_companies_table.php`
    - `database/migrations/*_create_company_user_table.php`
    - `database/migrations/*_create_roles_table.php`
    - `database/migrations/*_create_permissions_table.php`
    - `database/migrations/*_create_role_permission_table.php`
    - `database/migrations/*_add_core_columns_to_users_table.php` (current_company_id, timezone, locale, is_super_admin)

3. Add core models and relationships
    - `app/Core/Company/Models/Company.php`
    - `app/Core/RBAC/Models/Role.php`
    - `app/Core/RBAC/Models/Permission.php`
    - Update `app/Models/User.php` with company membership, roles, current company relation

4. Company context and scoping
    - `app/Core/Support/CompanyContext.php` to store resolved company
    - `app/Core/Support/CompanyScoped.php` trait to enforce `company_id`
    - Add global scope to company-owned models (start with Partner/Product once added)

5. Middleware and request pipeline
    - `app/Http/Middleware/ResolveCompanyContext.php` (subdomain/session/header)
    - `app/Http/Middleware/EnsureCompanyMembership.php`
    - Register middleware in `bootstrap/app.php` under web middleware stack

6. Authorization framework
    - Add `app/Providers/AuthServiceProvider.php`
    - Policies in `app/Policies/*` for Company, Role, Permission
    - Gate `before` for super admin

7. Inertia shared props
    - Update `app/Http/Middleware/HandleInertiaRequests.php` to include:
        - current company
        - allowed companies
        - permissions list
        - feature flags

8. Seeders for bootstrap
    - Update `database/seeders/DatabaseSeeder.php` to create company + admin role
    - Add `database/seeders/CoreRolesSeeder.php`

9. UI foundation updates
    - Add company switcher to `resources/js/layouts/app/app-header-layout.tsx`
    - Add permissions-aware navigation (hide/show items)
    - Add placeholder Core landing page in `resources/js/pages/core/overview.tsx`

### Phase 2: Core Business Master Data + Core Services

Goal: build the master data foundation that all ERP modules rely on.

1. Master data migrations
    - `partners`, `contacts`, `addresses`
    - `products`, `uoms`, `taxes`, `currencies`
    - Optional: `price_lists` for future pricing rules

2. Master data models and services
    - `app/Core/MasterData/Models/Partner.php`
    - `app/Core/MasterData/Models/Product.php`
    - Services for validation and defaults (tax, currency)

3. Controllers + Form Requests
    - `app/Http/Controllers/Core/PartnersController.php`
    - `app/Http/Controllers/Core/ProductsController.php`
    - `app/Http/Requests/Core/*` for validation

4. Routes and Inertia pages
    - Add routes to `routes/web.php` for CRUD
    - Create Inertia pages in `resources/js/pages/core/partners/*` and `resources/js/pages/core/products/*`
    - Use existing UI components for tables and forms

5. Policies and permissions
    - `app/Policies/PartnerPolicy.php`
    - `app/Policies/ProductPolicy.php`
    - Seed permissions in `CoreRolesSeeder`

6. External API scaffolding
    - Create `routes/api.php`
    - Add `/api/v1` endpoints for partners/products
    - Use Sanctum for token auth

### Phase 3: Audit, Settings, Attachments, Notifications

Goal: production-grade governance and operational utilities.

1. Audit logging
    - Migrations for `audit_logs` and `activity_logs`
    - Model observers for create/update/delete
    - UI page: `resources/js/pages/core/audit/index.tsx`

2. Settings service
    - `settings` table and `Setting` model
    - `app/Core/Support/SettingsService.php`
    - UI pages under `resources/js/pages/settings/core/*`

3. Attachments
    - `attachments` table and polymorphic relation
    - Storage config for local/S3
    - Access control policy

4. Notifications
    - In-app notifications table (Laravel default)
    - Mail templates for core events

### Phase 4: Hardening and QA

Goal: make the core stable, testable, and ready for production.

1. Tests
    - Feature tests for auth, company scoping, permissions
    - CRUD tests for partners/products

2. Performance and indexing
    - Ensure composite indexes on company_id
    - Add eager-loading patterns in controllers

3. Demo seeders
    - Sample partners, products, taxes, currencies

4. Developer tooling
    - Makefile or composer scripts for setup
    - Lint + typecheck passes
