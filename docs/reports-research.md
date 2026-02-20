# Platform Reports Research (2026)

## Sources reviewed

- Amplitude: North Star Metric guidance for activation, engagement, and retention focus.
  - https://amplitude.com/blog/north-star-metric
- Stripe: SaaS metrics and KPI baselines (MRR, churn, LTV, CAC, payback).
  - https://stripe.com/resources/more/saas-metrics-and-kpis
- Paddle: churn reporting distinctions (logo churn vs. revenue churn).
  - https://www.paddle.com/resources/saas-churn-rate

## What a SaaS platform should report

1. Growth and revenue quality:
   - New accounts, activation rates, MRR/ARR, expansion vs. contraction.
2. Retention and churn:
   - Logo churn, revenue churn, cohort retention trends.
3. Operational reliability:
   - Delivery success/failure rates, retries, incident/noisy-event trends.
4. Security and governance:
   - Admin actions, audit logs, privileged access and policy changes.
5. User lifecycle:
   - Invites issued/accepted/expired, role distribution, active user footprint.
6. Notification effectiveness:
   - Severity mix, escalation outcomes, read/open coverage.

## Implementation decisions for Port-101

- Added a dedicated superadmin reports center at `/platform/reports`.
- Centralized report filters in one place and reused them across report exports.
- Added report catalog coverage for:
  - Admin actions
  - Invite delivery trends
  - Company registry
  - Platform admins
  - Platform invites
  - Notification events
  - Platform performance snapshot
- Export format policy changed to only:
  - PDF (branded table report)
  - Excel `.xlsx` (title row + columns + data rows)

## Export template standards

- PDF: branded header with Port-101 mark/name, report title, subtitle/filter context, generated timestamp, and tabular rows.
- Excel: first row is report title, second row subtitle, followed by column headers and table rows.
