# Frontend Redesign Implementation Brief

This brief is based on the current implementation in `resources/js`, `resources/css/app.css`, `package.json`, and the page/component files in the repo. It is intended to support a full UX/UI redesign from the actual codebase, not assumptions.

## 1) Current stack and UI foundation

### Framework/runtime details

- Backend/runtime: Laravel + Inertia
- Frontend: React 19 + TypeScript
- Inertia adapter: `@inertiajs/react` 2.3.7
- Build/runtime: Vite 7 + Laravel Vite plugin
- SSR: present via `resources/js/ssr.tsx`
- CSS system: Tailwind CSS v4 via `@tailwindcss/vite`
- UI primitive layer: local shadcn-style components in `resources/js/components/ui/*` built on Radix primitives
- Additional headless library: `@headlessui/react`, lightly used in settings pages such as `resources/js/pages/settings/profile.tsx`

### Current form libraries

- Primary form model: Inertia `useForm`
- Secondary form model: Inertia `Form` component on starter-kit settings pages
- No React Hook Form
- No Formik
- No Zod/Yup client schema layer
- Validation UX is manual via server-returned errors and `InputError`

### Current table/grid libraries

- No real data-grid library
- No TanStack Table
- No AG Grid
- No MUI/X Grid
- Tables are mostly hand-built HTML tables with Tailwind classes inside `overflow-x-auto` wrappers

### Chart libraries

- Recharts
- Actual chart usage is concentrated in:
  - `resources/js/components/company/dashboard/*`
  - `resources/js/components/platform/dashboard/*`

### Icon library

- `lucide-react`

### Design tokens

Yes. Global CSS variable tokens already exist in `resources/css/app.css`.

Current token groups include:
- background / foreground / card / popover
- primary / secondary / accent / destructive
- border / input / ring
- chart colors `--chart-1` through `--chart-5`
- sidebar-specific tokens
- radius token
- font token

### Dark mode / light mode

Yes.

Implemented in `resources/js/hooks/use-appearance.tsx` with:
- `light`
- `dark`
- `system`

Theme state is persisted in `localStorage` and a cookie.

### Theming / white-label support

- Light/dark theming exists
- Token-based foundation exists
- White-label / tenant branding does not exist in the frontend implementation
- No company-specific runtime theme injection, brand palette override, or tenant logo/theme system was found

## 2) Route and overlay constraints

### Can modals/drawers be URL-addressable?

Yes, technically.

The app already uses URL query params and Inertia GET navigation extensively for filters, pagination, and some tab state. Query-driven overlays are feasible.

### Can browser back/forward preserve modal/drawer open state?

Only if overlay state is encoded in the URL.

`resources/js/app.tsx` installs a back/forward guard for authenticated pages and reloads on BFCache/back-forward navigation. That means local-only overlay state is not a reliable browser-history contract.

### Can overlays be opened from index pages without full navigation?

Yes, technically.

The component stack already contains:
- `Dialog`
- `Sheet`
- `DropdownMenu`

But business CRUD pages currently do not use modal/drawer workflows. Existing overlay usage is limited to small account/security/mobile-nav interactions.

### Do we already use query params for overlays, filters, tabs, or pagination?

- Filters: yes, widely
- Pagination: yes, widely
- Tabs: yes, selectively
- Overlays: not as a real business pattern yet

Actual query-driven page families include:
- platform dashboard/reports
- approvals
- company/core audit and report pages
- accounting statements
- HR indexes
- projects indexes
- inventory lots/cycle-counts/reordering
- integrations webhooks/deliveries

### Inertia constraints that make modal workflows difficult

Not impossible, but there are real constraints:
- current forms assume page-level create/edit routes
- success/failure handling assumes full-page context
- business overlays are not an established pattern yet
- browser-history support requires URL-state, not only React state
- there is no shared route-modal shell yet
- there is no client-side query/data cache layer separate from Inertia props

Practical conclusion:
- URL-addressable drawers/modals are feasible
- They should be built intentionally, not ad hoc
- Use query params or explicit nested routes if back/forward behavior matters

## 3) Surface classification audit

| module | surface | kind | complexity | approx field count | multi-section? | attachments? | line items? | approval workflow? | recommended container | why |
|---|---|---|---|---:|---|---|---|---|---|---|
| company | dashboard | dashboard | medium | 0 | yes | no | no | no | full page | command-center page with KPI cards, activity, quick actions, and role-aware content |
| company | settings | settings | high | 18-22 | yes | no | no | no | full page | long operational settings surface with many grouped controls |
| company | users directory | index | low | 0 | no | no | no | no | full page | now a read-only directory in `resources/js/pages/company/users.tsx` |
| company | owner invite | create | low | 2-3 | no | no | no | no | modal | small owner-only invite form |
| settings | profile | settings | low | 2 | no | no | no | no | drawer | compact account preference form |
| settings | password | settings | low | 3 | no | no | no | no | drawer | isolated credential update form |
| settings | two-factor auth | settings | medium | 0-2 | yes | no | no | no | full page + modal flow | page shell with an embedded setup modal already in use |
| settings | appearance | settings | low | 1 | no | no | no | no | drawer | small theme preference surface |
| settings | dashboard personalization | settings | medium | 4-6 | no | no | no | no | drawer | small preference surface |
| platform admin | platform dashboard | dashboard | high | 8+ filters | yes | no | no | no | full page | dense executive + operations hybrid |
| platform admin | governance | settings | high | 20+ | yes | no | no | no | full page | one of the largest settings pages in the app |
| platform admin | reports center | index | medium | 6+ filters | yes | no | no | no | full page | report catalog + presets + export surface |
| platform admin | queue health | dashboard/workbench | high | 2+ filters | yes | no | no | no | full page | operational triage workbench |
| platform admin | company create | create | medium | 7 | yes | no | no | no | drawer | compact enough for a large drawer |
| platform admin | company show | show/edit | medium | 6 + members table | yes | no | no | no | tabbed detail | behaves like a real detail page with related records |
| platform admin | platform admin invite | create | low | 2 | no | no | no | no | modal | ideal modal-sized admin surface |
| core/master data | partners | index | medium | 0 | no | no | no | no | full page | searchable data table |
| core/master data | partner create/edit | create/edit | medium | 6 | yes | yes | no | no | drawer | edit adds attachments but still stays compact |
| core/master data | contacts create/edit | create/edit | medium | 8-10 | no | edit: yes | no | no | drawer | contextual child-record editor |
| core/master data | addresses create/edit | create/edit | medium | 10-12 | no | edit: yes | no | no | drawer | relational child editor |
| core/master data | products | create/edit | high | 10-14 | yes | yes | yes | no | full page | attachments + bundle setup make it page-sized |
| core/master data | taxes/currencies/uoms | create/edit | low | 3-6 | no | edit: yes | no | no | modal | compact maintenance surfaces |
| core/master data | price list create/edit | create/edit | low | 4-5 | no | edit: yes | no | no | drawer | slightly richer than simple reference forms |
| core/master data | audit logs | index | medium | 5 filters | no | no | no | no | full page | investigation surface, not a modal candidate |
| core/master data | notifications | index | medium | 0 | no | no | no | no | full page | queue/inbox table |
| sales | dashboard | dashboard | medium | 0 | yes | no | no | no | full page | KPI + recent activity workspace |
| sales | leads create/edit | create/edit | medium | 6-10 | no | no | no | no | drawer | CRM record creation is contextual |
| sales | quote create/edit | create/edit | high | 4 header + line items | yes | no | yes | yes | full page | transactional authoring with embedded line editor |
| sales | order create/edit | create/edit | high | 4 header + line items | yes | no | yes | yes | full page | same weight as quotes |
| inventory | dashboard | dashboard | medium | 0 | yes | no | no | no | full page | KPI + recent tables |
| inventory | warehouse create/edit | create/edit | low | 4-5 | no | no | no | no | drawer | compact config record |
| inventory | location create/edit | create/edit | medium | 6-8 | no | no | no | no | drawer | contextual config surface |
| inventory | stock levels | index | medium | 0 | no | no | no | no | full page | dense read-only operational data |
| inventory | stock move create/edit | create/edit | high | 7-9 + tracked lines | yes | no | yes | yes | full page | tracked move-line editing and workflow actions |
| inventory | lot/serial show | show | medium | 0 | yes | no | no | no | tabbed detail | history/detail surface |
| inventory | cycle count create | create | medium | 6-8 | no | no | no | yes | full page | count scope setup |
| inventory | cycle count show | show | high | editable lines | yes | no | yes | yes | tabbed detail | operational session with lines and adjustments |
| inventory | reordering | index | high | filters + actions | yes | no | no | no | full page | replenishment workbench |
| inventory | reorder rule create/edit | create/edit | medium | 8-10 | no | no | no | no | drawer | contextual rule maintenance |
| purchasing | dashboard | dashboard | medium | 0 | yes | no | no | no | full page | KPI + recent RFQ/PO tables |
| purchasing | RFQ create/edit | create/edit | high | 3-5 + line items | yes | no | yes | yes | full page | transactional authoring surface |
| purchasing | purchase order create/edit | create/edit | high | 4 header + line items | yes | no | yes | yes | full page | downstream receipt flow keeps this page-sized |
| accounting | dashboard | dashboard | medium | 0 | yes | no | no | no | full page | finance overview/workbench |
| accounting | invoices | index | high | 0 | no | no | no | yes | full page | dense finance table |
| accounting | invoice create/edit | create/edit | high | 5 header + line items | yes | no | yes | yes | full page | transactional document editor |
| accounting | payments | index | high | 0 | no | no | no | yes | full page | workflow-heavy finance list |
| accounting | payment create/edit | create/edit | high | 5-7 + allocations | yes | no | yes-ish | yes | full page | payment application and reversal complexity |
| accounting | manual journal create/edit | create/edit | high | 6-8 + journal lines | yes | yes | yes | yes | full page | supporting docs + line editor + approval state |
| accounting | bank reconciliation | index/workbench | high | import + match + batch actions | yes | yes | yes | yes | split workbench | true workbench, not a standard form |
| accounting | ledger | index | high | 5 filters | no | no | no | no | full page | densest finance table in the app |
| projects | dashboard | dashboard | high | 0 | yes | no | no | no | full page | KPI + profitability + recurring + recent tables |
| projects | project create/edit | create/edit | high | 12-16 | yes | no | no | yes | full page | core project record is multi-section and billing-aware |
| projects | project show | show | high | 0 | yes | yes | yes | yes | tabbed detail | one of the largest detail pages in the app |
| projects | task create/edit | create/edit | medium | 6-8 | no | no | no | no | drawer | contextual to a project |
| projects | timesheet create/edit | create/edit | medium | 6-8 | no | no | no | yes | drawer | frequent contextual action |
| projects | milestone create/edit | create/edit | medium | 6-8 | no | no | no | yes | drawer | contextual to a project |
| projects | billables | index/workbench | high | 5 filters + bulk action | yes | no | no | yes | split workbench | queue-driven review surface |
| projects | recurring billing schedule create/edit | create/edit | high | 12-16 | yes | no | no | yes | full page | heavy commercial setup with conditional behavior |
| HR | dashboard | dashboard | medium | 0 | yes | no | no | no | full page | people ops landing page |
| HR | employees | index | high | 3 filters | no | no | no | no | full page | large people directory |
| HR | employee create/edit | create/edit | high | 28-35 | yes | no | no | no | full page | one of the largest forms in the app |
| HR | employee show | show | high | 0 | yes | yes | yes | no | tabbed detail | profile/access/contracts/documents structure already exists |
| HR | leave workspace | index | high | filters + multiple tables | yes | no | no | yes | full page | aggregated operational surface |
| HR | leave type create/edit | create/edit | low | 4-6 | no | no | no | no | modal | compact policy form |
| HR | leave period create/edit | create/edit | low | 4-5 | no | no | no | no | modal | compact period record |
| HR | leave allocation create/edit | create/edit | medium | 6-8 | no | no | no | no | drawer | contextual personnel action |
| HR | leave request create/edit | create/edit | medium | 7-9 | no | no | no | yes | drawer | frequent workflow item with draft/submit behavior |
| HR | attendance workspace | index | high | filters + multiple tables | yes | no | no | yes | full page | operational attendance hub |
| HR | shift create/edit | create/edit | low | 4-6 | no | no | no | no | modal | small config record |
| HR | shift assignment create/edit | create/edit | medium | 5-7 | no | no | no | no | drawer | contextual scheduling action |
| HR | attendance correction request create/edit | create/edit | medium | 5-7 | no | no | no | yes | drawer | frequent corrective action |
| HR | reimbursements workspace | index | high | filters + multiple tables | yes | no | no | yes | full page | multi-queue operational page |
| HR | reimbursement category create/edit | create/edit | low | 4-5 | no | no | no | no | modal | simple policy surface |
| HR | reimbursement claim create/edit | create/edit | high | 4 header + claim lines | yes | yes | yes | yes | full page | receipts, draft flow, and embedded line items |
| HR | payroll workspace | index | high | 0 | yes | no | no | yes | full page | payroll module workbench |
| HR | payroll structure create/edit | create/edit | high | 6-8 + structure lines | yes | no | yes | no | full page | embedded structure line editor |
| HR | payroll assignment create/edit | create/edit | medium | 10-12 | no | no | no | no | drawer | contextual compensation setup |
| HR | payroll period create/edit | create/edit | low | 6 | no | no | no | no | modal | compact period record |
| HR | payroll run create | create | medium | 4-6 | no | no | no | yes | drawer | kickoff flow is lightweight |
| HR | payroll run show | show | high | 0 | yes | no | yes | yes | tabbed detail | summary + work entries + payslips + actions |
| HR | payslip show | show | medium | 0 | yes | no | yes | no | summary header + tabs | detail surface already trends this way |
| HR | reports | index | medium | filters/export only | yes | no | no | no | full page | reporting surface |
| approvals | approvals queue | index/workbench | high | 4 filters | no | no | no | yes | full page | central decision queue |
| reports | company reports center | index | medium | filters + schedules + presets | yes | no | no | no | full page | export/catalog surface |
| integrations | integrations dashboard | dashboard | medium | 0 | yes | no | no | no | full page | endpoint health overview |
| integrations | webhook endpoints | index | high | filters + actions | no | no | no | no | full page | dense operational table |
| integrations | webhook endpoint create/edit | create/edit | medium | 4 + event selection | yes | no | no | no | full page | config + delivery behavior + event matrix |
| integrations | webhook endpoint show | show | high | 2 filters + detail + history | yes | no | no | no | tabbed detail | strong candidate for summary + tabs |
| integrations | delivery queue | index | high | 4 filters | no | no | no | no | full page | operational queue |
| integrations | delivery show | show | medium | 0 | yes | no | no | no | split detail | payload/response/evidence review fits split detail |

## 4) Form complexity review

### Forms that are simple enough for modal create/edit

- platform admin invite
- platform invite create
- company owner invite create
- currency create/edit
- UOM create/edit
- tax create/edit
- leave type create/edit
- leave period create/edit
- reimbursement category create/edit
- attendance shift create/edit
- payroll period create/edit

### Forms that should be right-side drawers instead

- partner create/edit
- contact create/edit
- address create/edit
- price list create/edit
- warehouse create/edit
- location create/edit
- leave allocation create/edit
- leave request create/edit
- shift assignment create/edit
- attendance correction request create/edit
- payroll assignment create/edit
- project task create/edit
- project timesheet create/edit
- project milestone create/edit

Reason: these are contextual records that benefit from keeping the list or parent detail visible behind the editor.

### Forms that must stay full pages

- company settings
- product create/edit
- sales quote create/edit
- sales order create/edit
- purchase RFQ create/edit
- purchase order create/edit
- accounting invoice create/edit
- accounting payment create/edit
- accounting manual journal create/edit
- inventory stock move create/edit
- accounting bank reconciliation
- project create/edit
- project recurring billing schedule create/edit
- HR employee create/edit
- HR reimbursement claim create/edit
- HR payroll structure create/edit

### Forms that need sticky footers

Current implementation does not provide a consistent sticky action bar. Redesign should add it to:
- product create/edit
- quote create/edit
- order create/edit
- RFQ create/edit
- PO create/edit
- invoice create/edit
- payment edit
- manual journal create/edit
- stock move create/edit
- project create/edit
- recurring billing schedule create/edit
- employee create/edit
- reimbursement claim create/edit
- payroll structure create/edit
- cycle count show
- project billables queue
- bank reconciliation workbench

### Forms that need autosave, save-as-draft, or unsaved-changes warnings

Current state:
- no global unsaved-changes guard found
- no autosave found

Need explicit draft or staged-save behavior:
- leave request create/edit
- attendance correction request create/edit
- reimbursement claim create/edit already models draft/save/submit
- payroll run already separates prepare/approve/post states

Need unsaved-changes warnings:
- employee create/edit
- product edit
- quote/order create/edit
- RFQ/PO create/edit
- invoice/payment/manual journal edit
- project create/edit
- recurring billing create/edit
- reimbursement claim form
- payroll structure form
- cycle count count-entry screen

### Forms that need multi-step wizard behavior

Not currently implemented as wizards, but strongest candidates are:
- platform company create
- employee onboarding with system access
- bank reconciliation import to match to post flow
- payroll run create to prepare to approve to post
- recurring billing schedule setup when auto-invoice is enabled

### Forms that need dependent/conditional sections

Actual current examples:
- product create/edit with `type`, `tracking_mode`, bundle settings, and components
- stock move create/edit with move type, tracking mode, lot/serial lines, and sales-order context
- invoice create/edit with `document_type` and linked-order availability
- purchase order create with RFQ-driven defaults
- employee create/edit with `requires_system_access`, linked user, role, and login email
- reimbursement claim with category-driven receipt requirements and line-level project assignment
- platform governance with delivery, recipient, and digest mode switches
- recurring billing with invoice automation and grouping rules
### Forms that need file upload and preview

Actual upload surfaces include:
- edit pages using `AttachmentsPanel`
- project show
- manual journal edit
- partner/contact/address/product/tax/currency/UOM/price list edit
- employee documents on employee show
- reimbursement line receipts
- bank statement import in bank reconciliation

### Forms that need embedded table editors / line-item editors

Actual current embedded editors:
- `resources/js/components/sales/line-items-editor.tsx`
- `resources/js/components/purchasing/line-items-editor.tsx`
- `resources/js/components/accounting/invoice-line-items-editor.tsx`
- `resources/js/components/accounting/manual-journal-lines-editor.tsx`
- `resources/js/components/inventory/inventory-move-lines-editor.tsx`
- reimbursement claim line editor
- payroll structure line editor
- cycle count count table

## 5) Table and list-page review

### Which tables require server-side pagination

Most major list surfaces already do. Confirmed families include:
- platform companies/admin-users/invites/queue health failed jobs
- core partners/contacts/addresses/products/taxes/currencies/uoms/price-lists
- core audit logs
- core notifications
- sales leads/quotes/orders
- inventory warehouses/locations/stock-levels/moves/lots/cycle-counts
- purchasing RFQs/orders
- accounting invoices/payments/ledger
- projects workspace/billables/recurring billing
- HR employees/leave/attendance/reimbursements/payroll payslips
- integrations webhooks/deliveries

### Which need search, filters, sort, bulk actions

Need search and filters:
- audit logs
- approvals queue
- HR employees
- HR leave workspace
- HR attendance workspace
- HR reimbursements
- projects workspace
- project billables
- inventory lots
- inventory cycle counts
- inventory reordering
- integrations webhooks
- integrations deliveries
- accounting ledger
- accounting statements
- company/platform reports centers
- platform queue health

Need stronger sort support:
- accounting ledger
- accounting invoices/payments
- approvals queue
- HR employees
- HR reimbursements
- project billables
- integrations deliveries
- integrations webhooks
- inventory stock levels
- inventory reordering suggestions

Need bulk actions:
- project billables
- bank reconciliation matched lines
- queue health failed jobs
- webhook dead-letter retry candidates
- approvals queue if batch decisions are added later

### Which need column visibility controls

Highest-value candidates:
- accounting ledger
- accounting invoices
- project billables
- integrations deliveries
- integrations webhooks
- inventory reordering suggestions
- HR reimbursements
- approvals queue
- HR employees
- platform queue health failed jobs

### Which need sticky headers or sticky first columns

High-density/wide tables:
- accounting ledger
- accounting bank reconciliation
- inventory reordering
- projects billables
- projects recurring billing
- integrations webhooks
- integrations deliveries
- HR reimbursements
- HR employees
- approvals queue
- inventory cycle count show

### Which need row selection

Current actual row-selection surfaces:
- project billables
- project show billables subtable
- bank reconciliation matched lines

Likely future row-selection candidates:
- approvals queue
- webhook deliveries
- queue health failed jobs

### Which need inline row actions vs kebab menu actions

Keep inline actions:
- small reference tables
- simple admin tables with 1-2 actions
- recent-record tables on dashboards

Move dense operational surfaces to kebab-menu actions:
- queue health failed jobs
- webhook deliveries
- webhook endpoints
- project billables
- inventory reordering suggestions
- HR reimbursements claims table
- bank reconciliation exception rows
- approvals queue
- employees table if action count grows further

### Which need inline editing

Current inline editing exists mainly in embedded editors, not generic list pages:
- cycle count count-entry table
- payroll structure lines
- reimbursement claim lines
- sales/purchasing/accounting line-item editors
- tracked inventory move lines

Recommendation: do not introduce generic inline editing to dense index tables first.

### Largest/highest-density tables in the app

By explicit `min-w-*` widths in the page code:
- `inventory/reordering/index.tsx` `min-w-[1480px]`
- `projects/recurring-billing/index.tsx` `min-w-[1480px]`
- `accounting/ledger/index.tsx` `min-w-[1460px]`
- `projects/billables/index.tsx` `min-w-[1380px]`
- `integrations/webhooks/index.tsx` `min-w-[1320px]`
- `hr/reimbursements/index.tsx` `min-w-[1300px]`
- `accounting/invoices/index.tsx` `min-w-[1300px]`
- `integrations/deliveries/index.tsx` `min-w-[1280px]`
- `inventory/cycle-counts/show.tsx` `min-w-[1280px]`
- `approvals/index.tsx` `min-w-[1240px]`

### Places where cards should remain cards and should not become tables

- dashboard KPI blocks
- report catalog cards in company and platform reports
- integrations dashboard endpoint cards
- integrations recent activity / dead-letter cards
- company dashboard quick actions
- project profitability cards
- platform alert incident cards
- webhook subscribed-events summaries
- employee access summary cards

## 6) Dashboard review

| dashboard | primary roles | top user questions | type | important charts | decorative vs critical KPIs | queues/alerts that need prominence |
|---|---|---|---|---|---|---|
| company dashboard | owner, company admin, module managers | What needs action today? What is failing? What does my role need next? | hybrid overview | activity trend, invite status mix | decorative: raw counts of owners/master data. critical: pending invites, failed invite deliveries, role-specific urgent metrics | recent activity, failed invites, role-aware quick actions |
| platform dashboard | superadmin | Are deliveries failing? Are admin actions abnormal? Are companies/invites growing? Which operations tab needs attention? | executive + operational hybrid | delivery trend, delivery status donut, noisy events | decorative: raw totals without operational meaning. critical: failure rate, pending delivery, noisy events, escalation coverage | delivery failures, invite delivery issues, operations tabs |
| sales dashboard | sales manager, owner | Where is pipeline stuck? How many leads/quotes/orders need movement? | operational overview | none currently | decorative: simple counts without age context. critical: stage distribution, open quotes/orders, pipeline value | open quotes, approvals, recent leads |
| inventory dashboard | inventory manager, operations | Where are shortages? Which moves/counts/replenishment items need action? | operational workbench | none currently | decorative: warehouse/location counts. critical: open cycle counts, replenishment suggestions, stock alerts | stock alerts, open cycle counts, reorder suggestions |
| purchasing dashboard | purchasing manager, operations | What RFQs/POs are open? What commitments are waiting? | operational overview | none currently | decorative: raw draft counts. critical: open commitments, open RFQs, recent POs | recent RFQs and POs |
| accounting dashboard | finance manager, accountant | What is overdue? What is open AR/AP? Are cash and statement snapshots healthy? | operational finance overview | none currently | decorative: chart-of-accounts/journal counts. critical: overdue invoices, open receivables, cash balance, recent reconciliation activity | recent invoices, recent payments, reconciliation access |
| projects dashboard | project manager, finance, owner | Which projects are at risk? What is ready to invoice? What is margin/utilization doing? | operational workbench | none currently | decorative: total project count. critical: at-risk projects, ready-to-invoice amount, pending approvals, margin, utilization | recent tasks, recent projects, billing queue, recurring due |
| HR dashboard | HR manager, HR officer | Which employee records/contracts/documents need attention? | operational overview | none currently | decorative: total docs only. critical: active employees, expiring docs, contracts ending soon | expiring docs, contracts ending soon |
| integrations dashboard | ops/integrations owner | Which endpoints are failing? How many dead letters and retries exist? | operational workbench | none currently | decorative: total endpoints. critical: failing endpoints, dead letters, success rate, average latency | dead-letter queue, failing endpoints, recent endpoint health |

Important implementation reality:
- only company and platform dashboards currently use real charts
- most module dashboards are KPI + table/card workbenches
- redesign should not force charts where the actual need is queue/workbench behavior

## 7) Detail/show-page review

### Which show pages should become tabbed detail pages

- project detail
- employee detail
- platform company detail
- webhook endpoint detail
- cycle count detail
- payroll run detail
- lot/serial detail
- integration delivery detail
- accounting invoice detail/edit
- accounting payment detail/edit

### Which should use summary header + tabs

- project show
- employee show
- platform company show
- webhook endpoint show
- payroll run show
- lot/serial show
- invoice detail/edit
- payment detail/edit

### Which should use split layout

Best candidates for `summary left / activity or evidence right`:
- webhook endpoint show
- integration delivery show
- project show
- employee show
- bank reconciliation workbench patterns
### Which need timeline/activity/attachments sections

- project show: yes
- employee show: documents are present, activity should be added or emphasized
- webhook endpoint show: delivery history + secret rotation history
- integration delivery show: payload/request/response evidence
- cycle count show: adjustment move history
- invoice/payment detail surfaces: should expose posting/reversal timeline better
- payroll run show: approval/posting timeline
- lot/serial show: movement timeline

### Which need sticky action bars

- project show
- cycle count show
- payroll run show
- invoice edit
- payment edit
- manual journal edit
- bank reconciliation
- project billables queue
- employee show access actions
- webhook endpoint show

### Which need related-record tables

- project show
- employee show
- platform company show
- webhook endpoint show
- cycle count show
- payroll run show
- lot/serial show

## 8) Responsiveness and device targets

### Is the app desktop-first only, or must tablet/mobile be good too?

Current implementation is desktop-first.

Navigation is mobile-aware, but transactional density is not mobile-optimized.

### Minimum supported viewport width

Uncertain as a formal product requirement. No explicit minimum is encoded.

Practical reality from the implementation:
- shell/settings/auth pages render at small widths
- dashboard cards mostly adapt
- productive use of dense ERP pages realistically wants `>= 1024px`

### Pages that currently break or degrade on smaller screens

They usually degrade into horizontal-scroll-heavy views rather than fully breaking:
- accounting ledger
- accounting bank reconciliation
- accounting invoices/payments
- approvals queue
- inventory reordering
- inventory cycle counts show
- inventory moves
- integrations webhooks
- integrations deliveries
- project billables
- project recurring billing
- HR employees
- HR reimbursements
- payroll structure form
- project show

### Whether dense tables are acceptable on mobile

Not as the primary mobile interaction pattern.

For dense operational surfaces, redesign should use:
- stacked cards
- row drawers
- compact action trays
- mobile detail flows

Dense tables as horizontal-scroll fallback are acceptable only for low-frequency admin use.

## 9) Permissions and workflow states

### How much the UI changes by role

A lot.

- Navigation is permission-gated in `resources/js/components/app-sidebar.tsx`
- Buttons and actions are permission-gated through `usePermissions()` and page props
- Company dashboard content changes materially by role
- HR access lifecycle now routes through employee records rather than generic user management

### Whether actions are hidden, disabled, or both

Both.

- Permissions usually hide surfaces/actions entirely
- Workflow/state often disables actions or replaces them with status text

### Where state/status complexity is highest

- accounting: invoices, payments, manual journals, bank reconciliation
- projects: billables, recurring billing, invoice draft handoff
- inventory: stock moves, cycle counts, reordering suggestions
- HR: leave requests, attendance corrections, reimbursement claims, payroll runs/payslips
- integrations: webhook delivery states, endpoint health, dead-letter/retry

### Which flows are risky/destructive and need extra confirmation UX

Current implementation often uses `window.confirm()` or `window.prompt()` on these kinds of flows:
- delete account
- delete webhook endpoint
- rotate webhook secret
- cancel/deactivate/reactivate employee access
- unreconcile bank batch
- reverse payment
- cancel stock move / cycle count
- discard failed job as poison
- retry/forget queue failures
- revoke invites
- delete master-data records

These should become designed confirmation flows rather than browser-native prompts.

## 10) Accessibility and internationalization

### Keyboard navigation gaps

- Dense table workflows are not optimized for keyboard operation
- Embedded editors are row-form UIs, not keyboard-first grids
- Business confirmations rely on `window.confirm()` / `window.prompt()`
- There is no consistent keyboard pattern for approvals, billables, reconciliation, or payroll line editing

### Focus management issues

- Focus management exists in small auth/settings dialogs
- It is not present as a general business pattern
- Filter submissions, table actions, and page refreshes do not restore meaningful focus targets
- Route-modal/drawer patterns will need explicit focus management if introduced

### Screen-reader labeling gaps

Good:
- many inputs have labels
- UI primitives include `aria-*` support
- some buttons include `sr-only` labels

Gaps:
- business tables generally lack captions
- workflow actions in row-heavy tables are not grouped semantically
- pagination is duplicated ad hoc and often uses `dangerouslySetInnerHTML`
- status color alone carries meaning in some places
- selection semantics are mostly visual

### Color-contrast issues already visible

- heavy use of `text-muted-foreground` on card/table backgrounds
- native control styling needed patching in CSS, especially select appearance
- chart and alert surfaces use subtle tints that need audit under both themes

### Date/time/currency formatting requirements

Current implementation is inconsistent:
- many pages use `new Date(...).toLocaleString()`
- some use `Intl.NumberFormat(undefined, ...)`
- some amounts are plain `.toFixed(2)` strings

Company settings store:
- `locale`
- `date_format`
- `number_format`
- `timezone`

But the frontend does not consistently honor those settings.

### Timezone, locale, RTL, and translation considerations

- Timezone exists in data model/settings and is partially used
- Locale exists in settings but is not consistently enforced in rendering
- No translation framework was found
- No RTL support was found

Conclusion:
- timezone support: partial
- locale-aware rendering: partial and inconsistent
- translation: absent
- RTL: absent

## 11) Technical and delivery constraints

### Risky areas to refactor

Most risky pages/components:
- `resources/js/pages/platform/dashboard.tsx`
- `resources/js/pages/platform/governance.tsx`
- `resources/js/pages/accounting/bank-reconciliation/index.tsx`
- `resources/js/pages/projects/projects/show.tsx`
- `resources/js/pages/hr/employees/show.tsx`
- `resources/js/pages/hr/employees/create.tsx`
- `resources/js/pages/hr/reimbursements/claims/claim-form.tsx`
- `resources/js/pages/hr/payroll/runs/show.tsx`
- `resources/js/components/app-sidebar.tsx`
- `resources/js/components/app-header.tsx`

Reason: these are large, stateful, role-aware, and action-heavy.

### Performance-sensitive pages

- platform dashboard
- platform governance
- platform queue health
- accounting bank reconciliation
- accounting ledger
- project show
- project billables
- integrations webhook show
- integrations delivery queue
- inventory reordering
- HR employees index/show
- HR reimbursements
- payroll run show

### Components that are tightly coupled and hard to redesign

- line-item editors for sales/purchasing/accounting are tightly coupled to page form state
- inventory move lines editor is tightly coupled to tracking logic
- settings starter-kit pages are coupled to generated route helpers and Inertia starter patterns
- page-level KPI and section cards are often implemented inline instead of through shared shells
- page-level pagination is duplicated across many screens

### Whether introducing a real data-grid is feasible

Yes, selectively.

Best candidates:
- accounting ledger
- accounting invoices
- accounting payments
- approvals queue
- HR employees
- inventory reordering suggestions
- project billables
- webhook deliveries
- webhook endpoints
- queue health failed jobs

Do not start by replacing every table. Low-density reference lists do not justify it.

### Whether introducing a token-based theme system is feasible

Yes.

The token foundation already exists in `resources/css/app.css`. The missing layer is stricter semantic token organization and more disciplined component consumption.

### Whether we can create shared page shells without breaking many screens

Yes, and this is high leverage.

Repeated patterns are everywhere:
- page header + action row
- KPI grid
- filter toolbar
- overflow table wrapper
- paginator
- detail card sections
- metric cards
- summary/detail rows

Strong candidates for shared redesign shells:
- `DashboardShell`
- `WorkspaceShell`
- `DetailShell`
- `FormShell`
- `FilterToolbar`
- `DataTableShell`
## 12) Visual references from the actual app

No screenshot assets are stored in the repo. Descriptions below are based on the actual TSX layouts.

| file | what it does | priority redesign target | screenshot description |
|---|---|---|---|
| `resources/js/pages/company/dashboard.tsx` | company command center with quick actions, KPI cards, charts, and recent activity | yes | gradient-style hero header, KPI card strip, two charts, quick-action cards, recent activity table |
| `resources/js/pages/platform/dashboard.tsx` | platform superadmin dashboard with delivery charts, noisy events, filters, presets, and tabbed operations detail | yes | dense analytics page with multiple KPI strips, charts, preset cards, filter forms, and tabbed data panels |
| `resources/js/pages/platform/governance.tsx` | governance settings for notification policy, alerting, and report delivery schedules | yes | large stacked settings sections with many selects/inputs and embedded analytics tables |
| `resources/js/pages/platform/operations/queue-health.tsx` | platform queue triage and dead-letter operations | yes | KPI strip on top, alert cards, backlog/failure tables, many operator action buttons |
| `resources/js/pages/accounting/bank-reconciliation/index.tsx` | statement import, match review, exception handling, and reconciliation batch creation | yes | multi-stage workbench with import form, metrics, matched-lines table, exception tables, and recent imports/batches |
| `resources/js/pages/accounting/invoices/create.tsx` | invoice/vendor bill creation with line-item editor and totals | yes | header form card, large editable line table, totals summary, bottom action row |
| `resources/js/pages/projects/projects/show.tsx` | full project detail with summary, tasks, timesheets, milestones, billables, invoices, recurring billing, attachments, and activity | yes | long project detail page with summary hero, KPI cards, many section tables, and file panel |
| `resources/js/pages/projects/billables/index.tsx` | project billables review queue with filters, approvals, selection, and invoice draft creation | yes | operational queue with filter bar, KPI cards, wide selectable table, and bulk action toolbar |
| `resources/js/pages/hr/employees/show.tsx` | employee detail with profile, access, contracts, and documents | yes | two-column detail cards at top, access management block, contracts table, and document upload area |
| `resources/js/pages/hr/employees/create.tsx` | large employee onboarding form with optional system access provisioning | yes | long multi-section form with access toggle section and many HR/personnel fields |
| `resources/js/pages/hr/reimbursements/claims/claim-form.tsx` | reimbursement claim editor with claim lines and receipt upload | yes | header card, line-item expense table, receipt upload cells, draft/submit behavior |
| `resources/js/pages/hr/payroll/runs/show.tsx` | payroll run execution detail with work entries, payslips, and approve/reject/post actions | yes | KPI strip, run detail card, two wide tables underneath |
| `resources/js/pages/inventory/reordering/index.tsx` | replenishment suggestions and reorder rule management | yes | KPI strip, status filter, wide suggestions table with vendor selectors and actions |
| `resources/js/pages/inventory/cycle-counts/show.tsx` | count session detail with editable count lines and generated adjustment moves | yes | KPI strip, approval context card, editable count table, adjustment move table |
| `resources/js/pages/integrations/webhooks/show.tsx` | webhook endpoint detail, analytics, secret rotation history, and delivery history | yes | endpoint summary cards, security/health metrics, subscribed-events cards, and a wide delivery-history table |
| `resources/js/pages/reports/index.tsx` | company reports center with export cards, presets, and schedules | medium | filter bar, report catalog cards, preset table, and schedule form |
| `resources/js/pages/integrations/index.tsx` | integrations dashboard with endpoint cards and dead-letter summary | medium | KPI strip, recent endpoint cards, event activity visuals, dead-letter summary cards |

## 13) Final recommendation summary

### 10 surfaces that should become modals

1. Platform admin invite create
2. Platform invite create
3. Company owner invite create
4. Currency create/edit
5. UOM create/edit
6. Tax create/edit
7. Leave type create/edit
8. Leave period create/edit
9. Reimbursement category create/edit
10. Attendance shift create/edit

### 10 surfaces that should become drawers

1. Partner create/edit
2. Contact create/edit
3. Address create/edit
4. Price list create/edit
5. Warehouse create/edit
6. Location create/edit
7. Leave allocation create/edit
8. Leave request create/edit
9. Attendance correction request create/edit
10. Payroll assignment create/edit

### 10 surfaces that must remain full pages

1. Company settings
2. Product create/edit
3. Sales quote create/edit
4. Sales order create/edit
5. Purchase RFQ create/edit
6. Purchase order create/edit
7. Accounting invoice create/edit
8. Inventory stock move create/edit
9. HR employee create/edit
10. Accounting bank reconciliation

### 10 surfaces that should become tabbed detail pages

1. Project detail
2. Employee detail
3. Platform company detail
4. Webhook endpoint detail
5. Cycle count detail
6. Payroll run detail
7. Lot/serial detail
8. Integration delivery detail
9. Accounting invoice detail/edit
10. Accounting payment detail/edit

### 10 highest-leverage reusable components to build first

1. `PageHeader`
2. `KpiCardGrid`
3. `FilterToolbar`
4. `DataTableShell`
5. `PaginationBar`
6. `StatusBadge`
7. `DetailHero`
8. `TabbedDetailShell`
9. `StickyFormFooter`
10. `EditableLineTable`

## Bottom line

The frontend is already structured enough to support a serious redesign, but it is not yet componentized around page archetypes. The biggest leverage is not cosmetic. It is turning repeated page patterns into reusable shells, then moving only genuinely small contextual forms into modals/drawers while leaving heavy ERP workflows as full pages or tabbed detail workbenches.
