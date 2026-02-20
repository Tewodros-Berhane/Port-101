# Dashboard Design (Superadmin + Platform)

## Scope

This document focuses on the superadmin platform dashboard at `resources/js/pages/platform/dashboard.tsx`.
Goal: reduce clutter, modernize the UI, and make high-impact operational signals obvious.

Research date: 2026-02-20.

## 1) 2026 React Dashboard Research

### 1.1 Core patterns that matter in 2026

- Keep interactions responsive under heavy updates:
  React `useTransition` is designed to keep UI responsive while non-urgent updates run, and `useDeferredValue` helps lag expensive derived UI behind fast input changes.
- Prefer server-first data delivery with selective hydration:
  Inertia supports partial reloads (`only`/`except`) so filters update only needed props, and deferred props (`Inertia::defer`) so heavy sections can load after first paint.
- Use controlled real-time updates:
  Inertia polling (`usePoll`) can refresh selected metrics on a cadence and can stop when the tab is not visible.
- Adopt charting and table tools based on complexity:
  Recharts offers a composable React-first API and is already aligned with shadcn chart wrappers; Apache ECharts offers advanced analytics visualizations and tree-shakable imports; TanStack Table plus virtualization handles large tables; AG Grid is a strong option for enterprise-grade grids.

### 1.2 Recommended package stack for this codebase

Current frontend stack already includes React 19, Tailwind 4, and shadcn/Radix-style primitives, so the best fit is:

| Area | Recommended | Why |
| --- | --- | --- |
| Charts (fast path) | `recharts` + shadcn `ChartContainer` | Fastest integration path with current component model and theme tokens. |
| Charts (advanced path) | `echarts` + `echarts-for-react` | Better for dense analytics (heatmaps, anomaly views, multi-axis, richer interaction). |
| Tables | `@tanstack/react-table` + virtualization | Headless control and strong compatibility with current Tailwind/styled table patterns. |
| Very large admin logs | `ag-grid-react` | Enterprise-grade sorting/filtering/grouping for large audit/admin datasets. |
| Server-state refresh | Inertia partial reload + deferred props + polling | Matches existing Laravel + Inertia architecture without unnecessary client cache complexity. |
| Input responsiveness | React `useTransition` / `useDeferredValue` | Avoids UI jank during filter updates and chart/table recomputation. |

### 1.3 2026 implementation principles for rich dashboards

- Decision-first hierarchy: top area should answer "Is the platform healthy?" in < 5 seconds.
- Visual-first analytics: replace trend tables with charts, keep tables for detail drill-down.
- Progressive disclosure: keep global filters visible, move low-frequency settings to drawers or dedicated settings pages.
- Task clarity: dashboard should primarily monitor and triage; configuration should be secondary.
- Performance by default: defer heavy data and render only what is visible.

## 2) Proposed Modern Superadmin Dashboard

### 2.1 Layout blueprint

1. Sticky command bar:
   Global time window, admin actor, action filter, quick reset, saved preset selector.
2. KPI strip (row 1):
   - Active companies ratio
   - Invite delivery success rate
   - Unacknowledged escalations
   - Digest open rate
3. Primary analytics (row 2):
   - Left (8 cols): Invite delivery trend (stacked area/column)
   - Right (4 cols): Escalation funnel + digest coverage donut
4. Secondary analytics (row 3):
   - Admin actions over time (bar/line)
   - Noisy-event leaderboard (horizontal bar)
5. Operations detail (row 4):
   Tabs for Companies, Invites, Admin Actions using one shared table shell.
6. Configuration access:
   Move "Scheduled export delivery" and "Notification governance controls" into:
   - `/platform/governance` page, or
   - right-side drawer opened from "Configure governance".

### 2.2 Interaction model

- Clicking any KPI opens a filtered detail tab/state.
- Filters update charts immediately; tables refresh via partial reload.
- Export actions live in a single split-button menu (CSV/JSON choices inside menu).
- Preset actions grouped under one menu (`Save`, `Rename`, `Delete`, `Set as default`).

### 2.3 Suggested component decomposition

- `PlatformDashboardPage`
- `PlatformDashboardFilterBar`
- `PlatformKpiGrid`
- `InviteDeliveryTrendChart`
- `AdminActionsTrendChart`
- `GovernanceAnalyticsPanel`
- `NoisyEventsPanel`
- `PlatformOperationsTabs`
- `OperationsExportMenu`

This replaces one very large page component with focused, testable sections.

### 2.4 Data loading strategy (Laravel + Inertia)

- Initial response: KPIs + top-level chart data only.
- Deferred props: recent tables and low-priority lists.
- Partial reload keys for filter changes:
  `deliverySummary`, `deliveryTrend`, `recentAdminActions`, `notificationGovernanceAnalytics`.
- Polling every 60-120s for KPI cards and governance counters only.

## 3) Platform Dashboard UX Problems Today

### 3.1 Structural issues

- Mixed intents in one screen:
  reporting + export scheduling + governance configuration + multiple operational tables are all stacked together in one flow (`resources/js/pages/platform/dashboard.tsx:233`, `resources/js/pages/platform/dashboard.tsx:511`, `resources/js/pages/platform/dashboard.tsx:688`).
- Page-level component is too large for maintainability and UX consistency:
  `resources/js/pages/platform/dashboard.tsx` is currently 1262 lines.
- Action overload at the top:
  filter submit/reset, preset save, and 4 export buttons are all in one row (`resources/js/pages/platform/dashboard.tsx:344`).

### 3.2 Visual hierarchy and clutter issues

- No true chart visuals for key trends:
  delivery trend is table-based instead of chart-based (`resources/js/pages/platform/dashboard.tsx:1086`).
- Too many same-weight bordered cards/tables create flat hierarchy and scanning fatigue (`resources/js/pages/platform/dashboard.tsx:905`, `resources/js/pages/platform/dashboard.tsx:1019`, `resources/js/pages/platform/dashboard.tsx:1051`).
- KPI cards are not actionable drill-down controls (`resources/js/pages/platform/dashboard.tsx:1019`).

### 3.3 Form UX and data quality issues

- Timezone is free-text in multiple places, which invites invalid values (`resources/js/pages/platform/dashboard.tsx:652`, `resources/js/pages/platform/dashboard.tsx:882`).
- Native selects are used extensively across dense forms, which has already caused dark mode legibility issues in the past (`resources/js/pages/platform/dashboard.tsx:258`, `resources/js/pages/platform/dashboard.tsx:715`).
- There are visible text-encoding artifacts for fallback separators in the UI (`resources/js/pages/platform/dashboard.tsx:1166`, `resources/js/pages/platform/dashboard.tsx:1233`).

## 4) Recommended rollout

1. Sprint 1 (high impact, low risk):
   add charts, simplify top action row, split governance controls out of dashboard, componentize page.
2. Sprint 2:
   add drill-down flows and tabbed operations views with shared filter state.
3. Sprint 3:
   add personalization (saved widget layout, default filter preset, per-admin dashboard preferences).

## References

- React `useTransition`: https://react.dev/reference/react/useTransition
- React `useDeferredValue`: https://react.dev/reference/react/useDeferredValue
- Inertia partial reloads: https://inertiajs.com/partial-reloads
- Inertia deferred props: https://inertiajs.com/deferred-props
- Inertia polling: https://inertiajs.com/polling
- TanStack Query important defaults: https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults
- TanStack Table virtualization guide: https://tanstack.com/table/latest/docs/guide/virtualization
- TanStack Table column filtering (client/server patterns): https://tanstack.com/table/latest/docs/guide/column-filtering
- AG Grid React: https://www.ag-grid.com/react-data-grid/
- Apache ECharts import/tree-shaking: https://echarts.apache.org/handbook/en/basics/import/
- Apache ECharts ARIA best practice: https://echarts.apache.org/handbook/en/best-practices/aria/
- shadcn Chart (built on Recharts): https://ui.shadcn.com/docs/components/chart
- Recharts README: https://raw.githubusercontent.com/recharts/recharts/main/README.md
- Nivo: https://nivo.rocks/
