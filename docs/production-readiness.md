# Production Readiness Checklist

## Purpose

This document defines the minimum bar for calling Port-101 production-ready.

It is intentionally stricter than "feature-complete" or "demo-ready".
Production-ready here means:

- the product can be operated safely for real customers
- failures are observable and recoverable
- integrations are stable enough to support external clients
- finance and inventory workflows have the controls expected from an ERP
- deployment, backup, and recovery are not ad hoc

## Current Verdict

As of `2026-03-27`, Port-101 is:

- `demo-ready`: yes
- `pilot-ready`: yes, with caution, for a controlled environment
- `production-ready`: no

Why:

- Core platform and major modules are implemented
- The automated suite is broad and currently green
- API v1, outbound webhooks, and the planned integration-hardening baseline are implemented
- But clean-environment restore sign-off on a real populated backup, staged load validation in a target environment, and production environment secret/retention verification are still incomplete

## Current Baseline

Current strengths:

- auth, RBAC, invite-only onboarding, company scoping
- master data, audit logs, settings, attachments, notifications
- sales, inventory, purchasing, accounting, approvals, reports, projects
- API v1 phases `0` through `7`
- webhook endpoint management and outbound event delivery
- advanced inventory depth (lots/serials, cycle counts, reordering, kits/bundles)
- idempotent retry protection on selected API v1 write actions
- structured logging and request correlation IDs across HTTP, queue, and scheduler flows
- queue health, dead-letter visibility, and operational alerting for failures/backlog drift
- backup/recovery runbook, cross-platform backup/restore scripts, and post-restore smoke-check tooling
- restore-signoff evidence tooling and cross-platform signoff wrappers
- disposable restore-drill automation for temporary database/storage validation
- deployment/rollback runbook and post-deploy smoke-check tooling
- performance audit tooling, hot-path index baseline migration, API smoke load-test harness, and load-summary validation/signoff tooling
- attachment upload/download hardening with MIME/extension allowlists, malware scan states, and quarantine download blocking
- API v1 rate limiting and webhook target validation for HTTPS/private/local-only safety
- nightly regression CI with retained test and performance-audit artifacts
- long-running integration CI with seeded operational smoke artifacts
- PostgreSQL-backed test suite

Current baseline evidence:

- latest full suite result: `274 passed`, `0 failed`
- build pipeline passes locally
- company and platform workflows are broadly covered by feature tests

## Release Gate Status

Use this legend:

- `[x]` complete
- `[~]` partial
- `[ ]` not complete

### 1. Product and Workflow Completeness

- `[x]` Core platform workflows are live
- `[x]` Sales MVP is live
- `[x]` Purchasing MVP is live
- `[x]` Accounting foundation is live
- `[x]` Projects/Services MVP is live
- `[x]` Inventory depth is complete for the current planned scope
- `[x]` Lots/serial numbers are implemented
- `[x]` Cycle counts are implemented
- `[x]` Reordering rules are implemented
- `[x]` Kits/bundles are implemented

Exit condition:

- all inventory depth items above are completed and verified

### 2. API and Integration Contract Maturity

- `[x]` Sanctum-protected API v1 exists
- `[x]` shared pagination, filtering, and JSON error contract exists
- `[x]` sales, inventory, purchasing, accounting, approvals, reports, projects, and webhooks are exposed in API v1
- `[x]` outbound webhook event publishing exists
- `[x]` webhook delivery and retry visibility exists
- `[x]` idempotency keys exist for write-heavy API actions
- `[x]` external reference fields exist on integration-heavy entities
- `[x]` webhook secret rotation history exists
- `[x]` replay-window verification policy is documented and enforced where required
- `[x]` API v1 deprecation/versioning policy is documented and emitted consistently

Exit condition:

- integrations must be safe against duplicate submissions, traceable to external systems, and governed by a stable contract policy

### 3. Observability and Operational Control

- `[~]` application behavior is testable and partially observable in production
- `[x]` structured logs exist across web, API, queue, and scheduler flows
- `[x]` request correlation IDs exist
- `[x]` queue job correlation propagation exists
- `[x]` webhook delivery analytics dashboard exists
- `[x]` queue failure dashboard exists
- `[x]` dead-letter tooling is complete beyond current webhook-level visibility
- `[x]` alerting exists for queue failures, job backlogs, delivery failures, and scheduler drift

Exit condition:

- operators can identify, trace, and act on failures without manual database inspection

### 4. Reliability and Recovery

- `[x]` core workflows are covered by automated tests
- `[~]` backup strategy is documented and script-automated, but environment scheduling/retention verification is still pending
- `[~]` restore procedure is documented, smoke-checked, has disposable drill automation, and now has sign-off evidence tooling, but a clean-environment sign-off drill against a real populated backup is still pending
- `[x]` disaster recovery runbook exists
- `[x]` queue retry tooling now includes poison-message decision support and a formal operator runbook
- `[~]` storage cleanup and retention operations are partially documented through the backup/recovery runbook

Exit condition:

- backups are scheduled in target environments, restoration has been tested in a clean drill, and critical failures have a written recovery path

### 5. Performance and Capacity

- `[~]` product works functionally and now has baseline performance tooling, but no formal production performance gate has been cleared
- `[~]` slow-query review has a baseline audit path, but no live `EXPLAIN` / `pg_stat_statements` sign-off has been completed
- `[x]` index review has been completed for the current high-volume tables
- `[ ]` queue throughput has been evaluated
- `[ ]` export/report generation has been tested under realistic volume
- `[ ]` webhook fan-out behavior has been tested under burst conditions
- `[~]` representative load-test harness and summary-validation tooling exist, but a real staged run has not been signed off yet

Exit condition:

- major workflows have known acceptable response and throughput behavior under expected load

### 6. Security and Governance

- `[x]` role-based authorization and company scoping are enforced
- `[x]` finance and approval workflows have baseline control structure
- `[~]` attachment support now has baseline hardening, but environment-level scanner operations and cloud-storage policy decisions are still pending
- `[x]` attachment virus scanning exists
- `[x]` strict MIME allowlists by module exist
- `[~]` webhook secret rotation exists, but broader credential runbook coverage is still incomplete
- `[~]` public API and webhook surfaces now have baseline hardening controls (rate limiting, HTTPS/private-target checks, replay/idempotency policy), but a final production review and credential checklist are still pending
- `[ ]` production environment secret-handling checklist is documented

Exit condition:

- externally exposed surfaces and file handling have explicit hardening controls and operational procedures

### 7. Delivery Process and Release Discipline

- `[x]` CI runs the test suite against PostgreSQL
- `[x]` nightly regression job exists
- `[x]` long-running integration job exists
- `[x]` deployment checklist exists
- `[x]` rollback procedure exists
- `[x]` production smoke-test checklist exists

Exit condition:

- every release has a repeatable verification, deployment, rollback, and post-deploy validation path

## Required Work Before Production

This is the minimum remaining implementation order.

### Phase 3: Recovery and Deployment Discipline

1. Backup operations completion
   - schedule database backup cadence in target environments
   - schedule storage backup cadence in target environments
   - verify retention behavior
   - confirm encryption/storage location choices

2. Restore drills
   - run the disposable restore drill against current backup artifacts
   - restore to a clean environment
   - run `php artisan ops:recovery:smoke-check`
   - verify app boots and critical workflows function
   - record `php artisan ops:recovery:signoff --write` evidence for the drill workspace

3. Target-environment verification
   - confirm scheduler heartbeat and queue workers are running in the target environment
   - confirm backup jobs, retention, and artifact storage are active in the target environment
   - record clean-environment restore sign-off evidence

### Phase 4: Performance Gate

1. Query/index review
   - run `php artisan ops:performance:audit`
   - retain the JSON artifact from `scripts/ops/run-performance-audit.*`
   - verify pagination and sorting paths under representative data
   - inspect reporting/export-heavy queries with `EXPLAIN` where needed

2. Load and burst testing
   - run the k6 API smoke harness
   - validate the retained summary with `php artisan ops:performance:validate-load <summary> --write`
   - invoice posting
   - project billing
   - inventory moves
   - webhook fan-out
   - report export jobs

3. Capacity baselines
   - expected tenants
   - expected data growth
   - queue throughput budget
   - storage growth budget

### Phase 5: Security Hardening

1. Attachment controls
   - verify the chosen malware-scan driver in the target environment
   - verify file-handling failure paths and operator visibility
   - decide cloud-storage signed-download policy if attachments move off the current private-disk baseline

2. Public surface review
   - validate API rate limits and webhook-target policies in a staging environment
   - review API v1 and webhook surfaces for auth, scoping, and replay expectations
   - document production secret-handling and credential rotation procedures
   - close the broader credential runbook gap beyond webhook secret rotation

## Strict Production Exit Criteria

Do not call the system production-ready until all of the following are true:

- idempotency exists for write-heavy API actions
- external reference mapping exists for integrations
- webhook governance hardening is complete
- structured logging is live and request/queue correlation IDs are live
- queue failure and dead-letter operations are visible in the UI or operator tooling
- alerting is configured
- backup and restore have been tested successfully in a clean environment
- deployment and rollback runbooks exist
- query/index review has been completed
- at least one representative load test has been run
- nightly regression automation exists

## Release Labels

Use these labels consistently.

### Demo-ready

Use when:

- product can be shown end-to-end
- seeded data is available
- major workflows are navigable

Port-101 status: `yes`

### Pilot-ready

Use when:

- small controlled tenant set is acceptable
- manual operations are still acceptable
- some failure handling may still require engineering involvement

Port-101 status: `yes, with caution`

### Production-ready

Use when:

- release gates in this document are closed
- operational ownership does not depend on ad hoc engineering intervention

Port-101 status: `not yet`

## Recommended Next Execution Order

1. clean-environment restore drill sign-off against a populated backup
2. performance/index review and staged load testing
3. target-environment backup scheduling and retention verification
4. production secret-handling and credential rotation checklist

## Ownership Rule

When evaluating future work, ask:

- does this make the product more feature-rich, or more operable?

Until the checklist above is closed, prefer operability and hardening work over new module expansion.
