# App Ownership Modes

## Purpose

Clarify who owns the ERP, how companies are created, and how registration works. This document defines two supported ownership modes, how to switch between them using a config file, and what parts of the app change depending on the mode.

## Reference: How Odoo Commonly Handles This

This ERP follows patterns similar to Odoo’s default behavior:

- **Instance creation is separate from company creation.** Odoo creates a database (instance) via a database manager or SaaS provisioning. After that, companies are created within the instance by admins.
- **Internal users are invited, not self-registered.** Public self‑signup is usually for portal/website users, not internal ERP roles.
- **Company creation is limited to admins.** Only users with administrative rights can create new companies inside an existing database.
- **Multi‑company is role‑driven.** Users have “allowed companies” and a “current company,” and access is constrained accordingly.
- **Superuser exists but is not part of normal workflows.** Odoo’s superuser (ID 1) is for maintenance and is typically not exposed in day‑to‑day UI.

These behaviors prevent a sales user from creating random companies and keep ownership clear.

## Chosen Ownership Mode

We will operate in **Platform‑Owned (SaaS)** mode.

- The platform operator owns the app and provisions companies.
- A **super admin** exists for platform oversight and support.
- Company owners manage their own company data, users, and permissions.
- Registration is **invite‑only** for internal users; external portal signup is optional.

## Configuration

We will use a config file to confirm platform‑owned mode. The config is not a deployment toggle; it is the documented single mode for this system.

## Registration and Company Creation Rules

- **Internal registration:** invite‑only.
- **Company creation:** only platform admins.
- **Owner creation:** platform admin creates the company and assigns the owner.
- **Prevent random companies:** disable self‑serve company creation for non‑admins.

## Seeder Behavior

- Create a **super admin** seed user.
- Do **not** create a company by default unless a demo setup is desired.
- Ensure super admin has platform‑level access.

## Super Admin Expectations

- Super admin exists and has platform visibility. Master data remains view‑only per current rules.

## Where This Affects Code

The following areas reflect platform‑owned rules:

- **Seeders**: `database/seeders/DatabaseSeeder.php`
- **User model**: `app/Models/User.php` (super admin logic)
- **Policies/Gates**: `app/Providers/AuthServiceProvider.php` and policy classes
- **Company creation routes/controllers** (when added)
- **Registration flow** (Fortify config and registration controllers)
- **UI navigation** (hide platform‑only sections in company‑owned mode)
- **Tests** (seeded super admin vs. first‑user company creation)

## If We Ever Remove Company‑Owned Mode

- Remove any self‑serve company creation logic.
- Keep registration invite‑only.
- Keep super admin and platform onboarding as the primary path.
