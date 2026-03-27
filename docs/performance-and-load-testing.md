# Performance And Load Testing

_Last updated: 2026-03-25_

## Purpose

Define the current performance-audit baseline for Port-101 and provide the operator workflow for index review and representative API load testing.

This document covers:

- the current hot-path table review
- the `ops:performance:audit` command
- the added index baseline migration
- the API smoke load-test harness
- the load-summary validation/sign-off command
- the remaining manual performance gates still required before production sign-off

This is an operations and readiness document, not a promise that production capacity has already been validated.

## Current Scope

The current performance baseline focuses on the highest-risk operational tables and surfaces already implemented in Port-101:

1. queue throughput tables:
   - `jobs`
   - `failed_jobs`
2. operator and notification surfaces:
   - `notifications`
   - `audit_logs`
3. report/export operations:
   - `report_exports`
4. integration and delivery operations:
   - `integration_events`
   - `webhook_endpoints`
   - `webhook_deliveries`

The goal is to make the existing dashboards, delivery tooling, retries, and operator workflows scale more predictably before broader production rollout.

## Implemented Index Baseline

The performance baseline migration adds composite indexes for the current hot paths:

- `jobs(queue, reserved_at, available_at)`
- `jobs(queue, available_at)`
- `failed_jobs(queue, failed_at)`
- `failed_jobs(failed_at)`
- `notifications(notifiable_type, notifiable_id, read_at, created_at)`
- `audit_logs(company_id, action, created_at)`
- `audit_logs(company_id, user_id, created_at)`
- `report_exports(status, failed_at)`
- `report_exports(company_id, status, failed_at)`
- `integration_events(company_id, published_at, occurred_at)`
- `webhook_endpoints(company_id, is_active, last_delivery_at)`
- `webhook_deliveries(status, dead_lettered_at)`
- `webhook_deliveries(company_id, status, dead_lettered_at)`
- `webhook_deliveries(company_id, webhook_endpoint_id, status)`
- `webhook_deliveries(company_id, status, next_retry_at)`

These indexes are designed around the actual application queries currently used by:

- queue-health monitoring
- alerting
- notification dropdowns
- audit-log browsing
- webhook endpoint/delivery workspaces
- report-export status and retry flows

## Performance Audit Command

Port-101 now includes:

```powershell
php artisan ops:performance:audit
```

This command:

1. verifies the hot-path tables exist
2. checks whether the expected indexes are present
3. reads PostgreSQL table statistics from `pg_stat_user_tables`
4. reports estimated rows, sequential scans, index scans, and size
5. emits recommendations when expected indexes are missing or sequential scans dominate

JSON output:

```powershell
php artisan ops:performance:audit --json
```

The JSON output is intended for retention alongside deployment/readiness evidence.

## Audit Wrapper Scripts

Cross-platform wrappers are available under `scripts/ops`:

- `scripts/ops/run-performance-audit.ps1`
- `scripts/ops/run-performance-audit.sh`

These wrappers:

1. run the audit
2. write JSON output into `storage/app/performance-audits`
3. keep a timestamped artifact for operator review

PowerShell:

```powershell
.\scripts\ops\run-performance-audit.ps1
```

Shell:

```bash
./scripts/ops/run-performance-audit.sh
```

## How To Interpret The Audit

Use the audit for baseline review, not as a substitute for real load testing.

### Healthy baseline

The audit is in a good baseline state when:

- all expected hot-path tables exist
- no expected indexes are missing
- PostgreSQL scan stats do not show obvious sequential-scan dominance on large operational tables

### Review required

Follow up when:

- any expected index is missing
- row counts are growing quickly and `seq_scan` continues to exceed `idx_scan`
- queue health, webhook delivery, or export pages become noticeably slower even when the audit passes

In those cases, inspect query plans directly in PostgreSQL using `EXPLAIN ANALYZE` and, where available in your environment, `pg_stat_statements`.

## API Smoke Load Harness

Port-101 now includes a baseline k6 harness at:

- `scripts/ops/k6-api-smoke.js`

Wrapper scripts:

- `scripts/ops/run-api-load-test.ps1`
- `scripts/ops/run-api-load-test.sh`

The harness currently exercises:

- `GET /api/v1/health`
- `GET /api/v1/projects`
- `GET /api/v1/inventory/stock-balances`
- `GET /api/v1/sales/orders`
- `GET /api/v1/webhooks/endpoints`

This is a representative read-path smoke harness for the current API v1 surface.

It is not yet the full production load gate for:

- invoice posting bursts
- project billing generation
- export creation floods
- webhook fan-out bursts

Those still require staged scenario execution against production-like data.

## Load-Test Prerequisites

You need:

1. `k6` installed and available on `PATH`
2. the application running locally or in a target test environment
3. a Sanctum bearer token for a user attached to an active company
4. representative seeded or staging data in the target environment

If `k6` is not installed, the wrapper scripts will fail fast with a clear error.

## Running The Load Harness

### PowerShell

```powershell
$env:API_TOKEN = "your-token"
.\scripts\ops\run-api-load-test.ps1 -BaseUrl "http://localhost:8000" -Vus 10 -Duration "60s"
```

### Shell

```bash
export API_TOKEN="your-token"
./scripts/ops/run-api-load-test.sh --base-url "http://localhost:8000" --vus 10 --duration "60s"
```

The wrapper writes a timestamped k6 summary JSON file under:

- `storage/app/load-tests`

By default, the wrapper now validates the retained summary with:

```powershell
php artisan ops:performance:validate-load <summary-json> --write
```

This writes a timestamped sign-off artifact under:

- `storage/app/load-signoffs`

## Initial Baseline Thresholds

The current k6 harness uses these initial thresholds:

- overall request failure rate: `< 2%`
- overall `p95` request duration: `< 1500ms`
- per-endpoint check success rate:
  - health: `> 99%`
  - protected list endpoints: `> 95%`

These are baseline smoke thresholds, not final production SLOs.

Tighten them once you have production-like data volume and measured operator expectations.

## Minimum Review Procedure Before Production Sign-Off

Do this in order:

1. run `php artisan migrate --force`
2. run `php artisan ops:performance:audit`
3. retain the JSON artifact from `scripts/ops/run-performance-audit.*`
4. run the k6 API smoke harness against representative seeded/staging data
5. validate the retained summary with `php artisan ops:performance:validate-load <summary-json> --write`
6. review:
   - p95 latency
   - error rate
   - queue impact during the run
   - webhook and report-export behavior during the run
6. document any tables or endpoints that still need query-plan review

## What Is Still Not Closed

This tooling does not by itself close the production performance gate.

The following still require execution and sign-off:

1. queue throughput evaluation under sustained load
2. realistic report-export generation tests
3. webhook fan-out burst testing
4. write-heavy scenario testing for finance and inventory actions
5. explicit capacity targets for tenants, jobs, and storage growth

Until those are executed, Port-101 should still be considered below full production readiness.
