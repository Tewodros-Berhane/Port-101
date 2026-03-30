# ERP Frontend Redesign Specification

## 0. Product Design Goal

Redesign the ERP so it feels:

- modern
- elegant
- trustworthy
- dense without feeling crowded
- fast for expert users
- scalable across all modules

This redesign should optimize for **clarity over decoration**. The goal is not a flashy SaaS look. The goal is a premium enterprise experience with strong hierarchy, better workflows, cleaner tables, calmer forms, and consistent operational feedback.

---

## 1. Design Principles

### 1.1 Core principles

1. **Clarity first**
   - Every screen should make it obvious what the user is looking at, what needs action, and what can happen next.

2. **Use the right container for the job**
   - Small tasks use modals.
   - Contextual edits use drawers.
   - Large workflows stay full pages.
   - Dense records use tabbed detail pages.

3. **One system, many modules**
   - The whole ERP should feel unified.
   - Each module can have a subtle accent and identity, but the interaction model should remain consistent.

4. **Operational pages prioritize scan speed**
   - Tables, queues, dashboards, and workbenches should optimize for filtering, comparison, and action-taking.

5. **Forms prioritize confidence**
   - Inputs, validation, help text, summaries, and save states should make large forms feel safe and controlled.

6. **Statuses must be consistent**
   - The same states should look the same across every module.

7. **Accessibility is part of the design**
   - Good contrast, keyboard support, focus states, readable typography, and color-independent meaning are mandatory.

---

## 2. Final Visual Direction

## 2.1 Design style

Use a **calm enterprise aesthetic**:

- slate/graphite neutrals
- one strong primary accent
- restrained semantic colors
- soft borders instead of heavy outlines
- minimal shadows
- clean typography
- generous but controlled spacing
- subtle motion only

Avoid:

- glassmorphism
- neon gradients
- over-coloring
- oversized rounded “consumer app” styling
- highly decorative cards
- visual clutter in dashboards and tables

---

## 3. Theme and Color System

## 3.1 Recommended theme: Slate + Cobalt ERP

### Light theme

- App background: `#F6F8FB`
- Surface: `#FFFFFF`
- Surface muted: `#F1F5F9`
- Border subtle: `#E2E8F0`
- Border strong: `#CBD5E1`
- Text primary: `#0F172A`
- Text secondary: `#475569`
- Text muted: `#64748B`

### Brand / action colors

- Primary: `#2563EB`
- Primary hover: `#1D4ED8`
- Primary soft background: `#DBEAFE`
- Primary soft text: `#1E40AF`

### Semantic colors

- Success: `#059669`
- Success soft: `#D1FAE5`
- Warning: `#D97706`
- Warning soft: `#FEF3C7`
- Danger: `#DC2626`
- Danger soft: `#FEE2E2`
- Info: `#0284C7`
- Info soft: `#E0F2FE`

### Dark theme

- App background: `#0B1220`
- Surface: `#111827`
- Surface muted: `#172033`
- Border subtle: `#243041`
- Border strong: `#334155`
- Text primary: `#E5EDF8`
- Text secondary: `#B6C2D2`
- Text muted: `#8DA0B8`
- Primary remains in the cobalt family but adjusted slightly brighter if needed for contrast

## 3.2 Module accents

Use module accents only for:

- active nav pill tint
- dashboard icon backgrounds
- chart accents
- selected section markers
- tiny identity indicators

Suggested mapping:

- Company / Platform: cobalt
- Sales: blue
- Inventory: teal
- Purchasing: cyan
- Accounting: indigo
- Projects: violet
- HR: emerald
- Integrations: sky

Do **not** color full pages by module.

## 3.3 Semantic token layer

Create semantic tokens on top of existing theme variables:

- `--bg-app`
- `--bg-surface`
- `--bg-surface-muted`
- `--bg-surface-elevated`
- `--text-primary`
- `--text-secondary`
- `--text-muted`
- `--border-subtle`
- `--border-default`
- `--border-strong`
- `--action-primary`
- `--action-primary-hover`
- `--action-danger`
- `--status-success`
- `--status-warning`
- `--status-danger`
- `--status-info`
- `--focus-ring`
- `--chart-1` to `--chart-6`

---

## 4. Typography

## 4.1 Font stack

- Primary UI font: **Inter**
- Monospace: **JetBrains Mono** or **IBM Plex Mono**

Use monospace only for:

- IDs
- webhook signatures
- invoice numbers
- ledger references
- timestamps in technical contexts
- JSON/payload values

## 4.2 Type scale

- Page title: `28/32`, semibold
- Section title: `18/24`, semibold
- Card title: `14/20`, medium
- Body: `14/20`
- Dense table text: `13/18`
- Meta / helper text: `12/16`

## 4.3 Typography rules

- Increase weight before increasing size
- Prefer two text tones on most screens: primary and secondary
- Use muted text only for truly secondary metadata
- Reduce all-caps usage to micro section labels only

---

## 5. Radius, Borders, Shadows, and Motion

## 5.1 Radius

- Inputs / buttons: `10px`
- Cards / filters / panels: `12px`
- Major dashboard / detail hero cards: `16px`

## 5.2 Borders

Borders should define structure more than shadows.

- Default border color should be subtle
- Section cards should be separated by tone and border, not heavy shadow

## 5.3 Shadows

Use very little shadow.

- Resting cards: nearly flat
- Floating menus: small shadow
- Drawers / dialogs: moderate shadow
- Avoid layered shadows on every component

## 5.4 Motion

Keep motion minimal and premium.

- Hover/focus transition: `120ms`
- Drawer open/close: `180–220ms`
- Modal open/close: `150–180ms`
- No bounce or playful motion

---

## 6. Global App Shell

## 6.1 Sidebar redesign

The sidebar is one of the highest-leverage redesign targets.

### New sidebar structure

Sections:

- Workspace
- Operations
- Master Data
- Governance
- Platform

### Behavior

- Width: around `272px`
- Small section labels in uppercase muted text
- Single icon style across all items
- Active item shown as a tinted pill
- Badge counts shown only for operationally meaningful items
- Tight but readable vertical spacing

### Active item treatment

- soft tinted background
- stronger text color
- slightly emphasized icon
- no heavy border-left bars

### Do not

- do not color every nav item differently
- do not use large filled backgrounds on every item
- do not put badges everywhere

## 6.2 Header redesign

### Left side

- Page title
- Breadcrumbs only when needed
- Optional short subtitle on dense pages

### Right side

- global search / command bar trigger
- notifications
- company switcher
- user menu

### Behavior

- sticky on scroll
- subtle bottom border
- cleaner spacing
- quieter visual hierarchy than body content

## 6.3 Command bar

Add `⌘K` / `Ctrl+K` command palette for:

- jumping between modules
- opening recent records
- creating common entities
- opening reports
- navigating to settings pages
- quick global search

---

## 7. Shared Page Archetypes

Build shared shells before redesigning individual pages.

## 7.1 `WorkspaceShell`

Use for operational list-heavy pages.

### Structure

1. `PageHeader`
2. optional `KpiStrip`
3. `FilterToolbar`
4. `DataTableShell`
5. `PaginationBar`

### Use for

- approvals
- accounting ledger
- project billables
- integrations deliveries
- inventory reordering
- HR reimbursements
- queue health
- webhook endpoints

## 7.2 `FormShell`

Use for create/edit flows.

### Structure

1. page header
2. section cards
3. optional side summary
4. sticky footer
5. unsaved changes guard

## 7.3 `TabbedDetailShell`

Use for dense record detail pages.

### Structure

1. `DetailHero`
2. sticky action row where needed
3. tab navigation
4. tab content panels

## 7.4 `DashboardShell`

Two modes:

- overview dashboard
- operational workbench dashboard

---

## 8. Modals, Drawers, Full Pages, and Tabbed Details

## 8.1 Modal rules

Use a modal when:

- under 6–8 meaningful fields
- one quick action or creation
- no attachments
- no line items
- no long instructions
- no dependency on seeing the parent page while working

### Modal targets

- platform admin invite
- platform/company invite create
- owner invite create
- tax create/edit
- currency create/edit
- UOM create/edit
- leave type create/edit
- leave period create/edit
- reimbursement category create/edit
- attendance shift create/edit

## 8.2 Drawer rules

Use a right drawer when:

- the record is contextual to a list or parent record
- the user benefits from keeping surrounding context visible
- the form is medium-sized
- there are no heavy line items
- there is no large workflow document authoring

### Drawer targets

- partner create/edit
- contact create/edit
- address create/edit
- warehouse create/edit
- location create/edit
- price list create/edit
- leave allocation create/edit
- leave request create/edit
- attendance correction request create/edit
- payroll assignment create/edit
- task create/edit
- timesheet create/edit
- milestone create/edit
- platform company create

## 8.3 Full page rules

Keep full pages when the workflow is dense or document-like.

### Full page targets

- company settings
- governance settings
- product create/edit
- quote create/edit
- order create/edit
- RFQ create/edit
- purchase order create/edit
- accounting invoice create/edit
- accounting payment create/edit
- accounting manual journal create/edit
- stock move create/edit
- cycle count create
- bank reconciliation
- project create/edit
- recurring billing schedule create/edit
- employee create/edit
- reimbursement claim create/edit
- payroll structure create/edit

## 8.4 Tabbed detail page rules

Use summary header + tabs for detail pages that currently stack too much vertical information.

### Tabbed detail targets

- project detail
- employee detail
- platform company detail
- webhook endpoint detail
- integration delivery detail
- cycle count detail
- payroll run detail
- lot/serial detail
- accounting invoice detail
- accounting payment detail

---

## 9. Table and List Page Redesign

## 9.1 `DataTableShell`

Every major table page should use one shared shell.

### Top header area

- title
- short description
- result count
- saved view selector
- primary CTA

### Toolbar

- search
- filters
- sort
- density control
- column visibility
- export
- bulk actions when rows are selected

### Table body

- sticky header
- strong row scan hierarchy
- clear hover state
- selected row state
- optional zebra only for dense tables
- primary identity in first column
- actions in final column

### Footer

- page size
- page number
- total count
- next / previous
- optional jump-to-page for very large tables

## 9.2 Density system

Create 3 table densities:

- Comfortable
- Default
- Compact

Recommended usage:

- Comfortable: settings/reference lists
- Default: most operational lists
- Compact: ledger, approvals, deliveries, billables, reordering

## 9.3 Action patterns

### Inline actions

Use only when there are one or two obvious actions.

### Kebab menu actions

Use on dense operational tables:

- approvals queue
- deliveries
- webhook endpoints
- reordering suggestions
- queue health
- reimbursements table
- employee table if action count increases

## 9.4 Row selection

Use row selection only where batch actions matter.

Priority row-selection pages:

- project billables
- bank reconciliation matched lines
- approvals queue
- queue health retry/discard flows
- deliveries if batch retry becomes available

## 9.5 Column visibility

Add column visibility controls first to:

- accounting ledger
- accounting invoices
- accounting payments
- project billables
- integrations deliveries
- integrations webhooks
- inventory reordering
- approvals queue
- HR employees
- HR reimbursements

## 9.6 Sticky table behavior

Add sticky headers to:

- ledger
- bank reconciliation
- reordering
- billables
- recurring billing
- deliveries
- webhooks
- approvals
- reimbursements
- cycle count detail lines

Add sticky first column where identity loss is a problem.

## 9.7 Empty states

Each empty state must include:

- clear title
- one-sentence explanation
- primary action
- optional helper action

Examples:

- No invoices yet
- No matching deliveries
- No reorder suggestions
- No employees match this filter
- No approvals pending

Do not use a blank white card with a line of muted text.

---

## 10. Form Redesign

## 10.1 `FormShell` anatomy

### Header

- title
- supporting text
- status chip if editing
- optional draft/last-updated metadata

### Body

Use section cards with consistent spacing.

Examples of section names:

- General
- Details
- Financial
- Assignment
- Access
- Workflow
- Files
- Notes

### Footer

Sticky action footer with:

- Cancel
- Save draft when relevant
- Save
- primary workflow action

## 10.2 Form layout rules

- 1-column for simple forms
- 2-column for medium forms
- 2-column main + sticky summary aside for transactional forms
- no 3-column business forms

## 10.3 Input rules

- labels always visible
- helper text under complex fields
- consistent error placement
- optional input prefix/suffix where useful
- replace inconsistent native-select styling with a standard select/combobox pattern

## 10.4 Validation UX

- inline field errors
- section-level summaries for large forms
- scroll and focus to first invalid field
- save success via toast or inline success bar
- unsaved changes warning on navigation

## 10.5 Forms that must get sticky footers first

- product
- quote
- order
- RFQ
- PO
- invoice
- payment
- manual journal
- stock move
- project
- recurring billing
- employee
- reimbursement claim
- payroll structure
- cycle count entry
- bank reconciliation actions

## 10.6 Draft flows

Add save-as-draft or staged-save behavior to:

- leave request
- attendance correction request
- reimbursement claim
- payroll run setup
- recurring billing configuration where automation is involved

---

## 11. Line-Item and Structured Editors

These are high-value redesign targets and should become one of the strongest parts of the app.

## 11.1 New `EditableLineTable`

Use a single design language across:

- sales line items
- purchasing line items
- invoice lines
- manual journal lines
- inventory move lines
- reimbursement lines
- payroll structure lines
- cycle count tables

## 11.2 Interaction model

- compact row density
- fixed header row
- inline numeric alignment
- row validation states
- keyboard-friendly navigation
- row add button at bottom
- destructive remove action clearly separated
- totals/subtotals always visible
- optional row inspector for selected row if the table becomes too dense

## 11.3 Visual treatment

- light grid lines
- emphasized numeric columns
- sticky summary area
- distinct draft/error/warning row states

---

## 12. Detail / Show Page Redesign

## 12.1 `DetailHero`

Each detail page should begin with a summary hero.

### Include

- record title
- status badge
- key metadata
- 2–4 KPI or summary stats
- primary actions
- secondary actions in menu

## 12.2 Tabs

Standardize a shared tab pattern.

Common tabs:

- Overview
- Activity
- Attachments
- Related Records
- History
- Settings / Access

## 12.3 Module-specific detail tab recommendations

### Project detail

- Overview
- Tasks
- Timesheets
- Milestones
- Billables
- Invoices
- Files
- Activity

### Employee detail

- Profile
- Employment
- Access
- Leave
- Attendance
- Payroll
- Documents
- Activity

### Webhook endpoint detail

- Overview
- Deliveries
- Security
- Events
- History

### Payroll run detail

- Summary
- Work Entries
- Payslips
- Approval History

### Cycle count detail

- Summary
- Count Lines
- Adjustments
- Activity

### Platform company detail

- Overview
- Members
- Modules
- Settings
- Activity

### Lot/serial detail

- Overview
- Movement History
- Related Transactions

---

## 13. Dashboard Redesign

## 13.1 Dashboard modes

### Overview dashboards

Use for:

- company dashboard
- platform dashboard

### Operational workbench dashboards

Use for:

- inventory
- accounting
- projects
- HR
- integrations
- queue health-style surfaces

## 13.2 Dashboard building blocks

Create shared components:

- `MetricCard`
- `MetricCardGrid`
- `ChartCard`
- `QueueCard`
- `AlertCard`
- `ActivityCard`
- `QuickActionCard`

## 13.3 KPI rules

A KPI card must answer one of these:

- what changed
- what needs action
- what is at risk
- what deserves attention

Avoid decorative totals that have no next step.

## 13.4 Chart rules

- do not force charts into all modules
- use charts only where change over time, distribution, or comparison matters
- keep chart colors consistent across the app
- use queue/workbench cards where action is more important than analytics

## 13.5 Dashboard layout rules

### Overview dashboards

1. headline summary
2. 4–6 KPI cards max
3. 2–3 important charts
4. key alerts / failures
5. recent activity

### Workbench dashboards

1. status summary cards
2. primary queue
3. secondary queue
4. recent exceptions
5. quick actions

---

## 14. Status System

## 14.1 Standard status language

Create one central badge system for:

- Draft
- Pending
- Approved
- Rejected
- Active
- Inactive
- Posted
- Failed
- Delivered
- Cancelled
- In Progress
- Overdue

## 14.2 Badge rules

Each badge should have:

- semantic color
- optional icon
- readable contrast
- same meaning everywhere in the app

Do not use random text colors or one-off chip styles inside pages.

---

## 15. Feedback, Alerts, and Confirmations

## 15.1 Toasts

Use for short success or info messages only.

Examples:

- Invoice saved
- Employee updated
- Delivery retried

## 15.2 Inline alerts

Use for:

- partial failures
- stale data
- filter result messages
- warning banners in forms

## 15.3 Confirmation flows

Replace browser-native confirmations with designed components:

- `ConfirmDialog`
- `DestructiveDialog`
- `ReasonDialog`
- `BulkActionDialog`

Use these for:

- delete actions
- reverse actions
- cancel/void operations
- retry/discard flows
- secret rotation
- invite revocation
- access deactivation

---

## 16. Accessibility Requirements

## 16.1 Non-negotiables

- meet contrast requirements for text and essential UI
- never rely on color alone for meaning
- every icon-only action needs an accessible name
- tables need proper semantic structure
- modals and drawers need focus management
- keyboard support for dense table/editor workflows
- visible focus states

## 16.2 App-specific upgrades

- centralize date/number/currency formatting
- reduce overuse of muted text
- add table captions where useful
- restore meaningful focus after major actions
- make approval and line-editor flows keyboard-friendly

---

## 17. Responsive Strategy

## 17.1 Breakpoint philosophy

This ERP is desktop-first, but smaller screens should still be graceful.

### Desktop

Primary target for dense workflows

### Tablet

Reasonable support for dashboard, detail, and moderate forms

### Mobile

Support light admin tasks, approvals, lookups, notifications, and small edits

## 17.2 Mobile behavior rules

For dense pages on mobile:

- convert wide tables to stacked record cards where needed
- open row detail in drawers or detail pages
- move secondary actions into menus
- use compact filter sheets
- do not keep giant horizontally scrolling tables as the main intended experience

---

## 18. Implementation Order

## Phase 1 — Foundations

Build first:

- semantic tokens
- typography scale
- radius and shadow scale
- restyled primitives
- status badge system
- dark theme polish
- focus ring system

## Phase 2 — Shell

Build:

- redesigned sidebar
- redesigned header
- command palette
- notification panel
- breadcrumb cleanup

## Phase 3 — Shared page shells

Build:

- `PageHeader`
- `WorkspaceShell`
- `FilterToolbar`
- `DataTableShell`
- `PaginationBar`
- `FormShell`
- `StickyFormFooter`
- `DetailHero`
- `TabbedDetailShell`
- `MetricCard` system

## Phase 4 — Highest-impact screens

Redesign first:

- platform dashboard
- accounting ledger
- bank reconciliation
- project show
- employee show
- employee create/edit
- project billables
- inventory reordering
- webhook endpoint detail
- integrations deliveries
- approvals queue

## Phase 5 — Modal rollout

Convert small forms first:

- invites
- taxes
- currencies
- UOMs
- leave types
- leave periods
- reimbursement categories
- shifts

## Phase 6 — Drawer rollout

Convert contextual forms:

- partner/contact/address
- warehouse/location
- leave flows
- attendance correction
- payroll assignment
- task/timesheet/milestone

## Phase 7 — Detail page tabbing

Convert:

- project
- employee
- payroll run
- lot/serial
- platform company
- webhook endpoint
- cycle count
- invoice/payment detail

---

## 19. Reusable Components to Build First

1. `PageHeader`
2. `MetricCard`
3. `MetricCardGrid`
4. `FilterToolbar`
5. `DataTableShell`
6. `PaginationBar`
7. `StatusBadge`
8. `FormShell`
9. `StickyFormFooter`
10. `DetailHero`
11. `TabbedDetailShell`
12. `EditableLineTable`
13. `ConfirmDialog`
14. `BulkActionDialog`
15. `EmptyState`
16. `ActivityFeedCard`
17. `QuickActionCard`
18. `SectionCard`

---

## 20. Final Direction Summary

This redesign should make the ERP feel:

- cleaner without losing power
- more premium without becoming flashy
- denser without becoming cramped
- more consistent across modules
- safer for complex workflows
- easier to scan, filter, and act in

The visual identity should come from:

- excellent spacing
- strong hierarchy
- calm neutrals
- disciplined accent usage
- smarter containers
- better tables
- cleaner forms
- better status language
- polished interactions

That is the right modern direction for this product.
