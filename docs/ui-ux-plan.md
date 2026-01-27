# UI/UX Plan (Core Platform + Landing)

## Purpose

Define the UI structure, UX practices, and page map for the ERP so implementation stays consistent, scalable, and production-ready. This plan covers the core/platform UI, public landing, and shared patterns.

## Current UI Inventory (Already Implemented)

Based on the current codebase:

- **Public**: `resources/js/pages/welcome.tsx` (starter landing placeholder)
- **App**: `resources/js/pages/dashboard.tsx` (placeholder dashboard)
- **Auth**: login/register/forgot/reset/confirm/verify/2FA challenge in `resources/js/pages/auth/*`
- **Settings**: profile/password/appearance/2FA in `resources/js/pages/settings/*`
- **Layouts**: `resources/js/layouts/app-layout.tsx`, `resources/js/layouts/app/*`, `resources/js/layouts/auth/*`, `resources/js/layouts/settings/layout.tsx`
- **Navigation**: sidebar + header components exist; `NavMain` is a single group labeled “Platform”
- **Theme tokens**: CSS variables and Tailwind theme in `resources/css/app.css`

## UX Goals

- **Speed to task**: common workflows reachable in 1–2 clicks from dashboard.
- **Clarity over density**: progressive disclosure, clear hierarchy, minimal cognitive load.
- **Consistency**: every module uses the same list/detail/edit patterns.
- **Trust and auditability**: surface status, history, and approvals clearly.
- **Scalable navigation**: modules appear only when the user has permission.

## Design System & Best Practices

### Typography

- Use a distinct, readable sans-serif (current base is Instrument Sans).
- Establish scale: page title, section header, body, helper text.
- Maintain 1.4–1.6 line-height for body text.

### Color & Theme

- Use CSS variables for theme tokens (already in `resources/css/app.css`).
- Neutral base with a single strong accent for primary actions.
- Ensure color contrast meets WCAG AA.

### Spacing & Layout

- Use an 8px spacing scale (Tailwind spacing tokens).
- Keep consistent padding in cards, tables, and forms.
- Use grid for layout; avoid fixed widths except in dialogs.

### Components

- Reuse shared UI components (`resources/js/components/ui/*`).
- Prefer composition over custom one-off components.
- Add new UI patterns as reusable components, not inline CSS.

### Motion

- Use subtle transitions for sidebar, modals, and toasts.
- Keep motion meaningful; avoid distracting animations.

## Information Architecture (Core)

Navigation is permission-driven and grouped by business area.

Proposed top-level groups:

- **Core**: Dashboard, Company, Users, Roles & Permissions, Master Data, Audit Log
- **Settings**: Company settings, User settings
- **Operations (later)**: Sales, Purchases, Inventory, Accounting

## Core Platform Page Map (MVP)

### 1) Dashboard

- KPIs: cash position, receivables, payables, sales trend, inventory snapshot
- Quick actions: create customer, product, invoice, order
- Pending approvals/alerts

### 2) Company

- Company profile (name, timezone, currency, fiscal year)
- Branches (future)
- Company switcher in header

### 3) Users

- User list, invite, deactivate
- Assign role per company

### 4) Roles & Permissions

- Roles list and detail
- Permission assignment by role
- Default role templates (Owner, Manager, Staff)

### 5) Master Data

- Partners (customers/vendors)
- Products
- Taxes
- Currencies
- Units of Measure

### 6) Audit Log

- Global audit feed
- Filters by user, entity, date

### 7) Settings

- User settings: profile, password, 2FA, appearance
- Company settings: defaults and localization

## Landing Page (Public)

The public landing page should explain value, differentiate from Odoo, and drive sign-up.

Suggested structure:

- Hero: product promise + CTA
- Value props: faster setup, modular scale, clean UX
- Module highlights: Sales, Inventory, Accounting
- Proof: screenshots or product tour
- Security and reliability notes
- Pricing/plan teaser
- Footer: docs, contact, legal

## Page Templates & Patterns

### List Pages

- Search + filters + bulk actions
- Sortable columns
- Pagination and saved views
- Empty state with CTA

### Detail Pages

- Header with status and actions
- Summary cards + activity timeline
- Related records tabs (orders, invoices, payments)

### Create/Edit Forms

- Sectioned form layout
- Inline validation
- Sticky action bar for save/cancel

### Settings Pages

- Left-side nav, content on right
- Each setting group is a card

### Wizards

- For onboarding and setup
- Stepper with progress and validation

## Responsive Design Plan

- Sidebar collapses to icons on tablet, becomes a drawer on mobile.
- Tables become stacked cards on small screens.
- Action bars collapse into overflow menus.
- Forms become single-column; avoid multi-column on mobile.

## Accessibility

- Keyboard navigable components (menus, tabs, dialogs)
- Visible focus states
- Proper labels and ARIA attributes
- Error messages announced and linked to fields

## Data Handling

- Server-side pagination for large datasets
- Debounced search
- Consistent loading states (skeletons)
- Clear empty and error states

## Implementation Conventions

- Inertia pages in `resources/js/pages`.
- Layouts in `resources/js/layouts`.
- Shared UI components in `resources/js/components`.
- Use `AppLayout` for authenticated pages; `AuthLayout` for auth.
- Page titles set via `Head`.

## Open Decisions

- Final color palette and accent color
- Landing page content (copy and screenshots)
- Dashboard KPIs for first release

## Next Steps

1. Replace `welcome.tsx` with ERP landing page design.
2. Build core navigation structure and permission filters.
3. Implement dashboard KPI widgets.
4. Start core master data CRUD pages using shared patterns.
