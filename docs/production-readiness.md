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

As of `2026-03-24`, Port-101 is:

- `demo-ready`: yes
- `pilot-ready`: mostly yes, for a controlled environment
- `production-ready`: no

Why:

- Core platform and major modules are implemented
- The automated suite is broad and currently green
- API v1 and outbound webhooks exist
- But integration hardening and production operations hardening are still incomplete

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
- backup/recovery runbook, cross-platform backup/restore scripts, and post-restore smoke-check tooling
- PostgreSQL-backed test suite

Current baseline evidence:

- latest full suite result: `259 passed`, `0 failed`
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
- `[~]` restore procedure is documented and smoke-checked, but a clean-environment drill is still pending
- `[x]` disaster recovery runbook exists
- `[ ]` queue retry and poison-message handling policy exists
- `[~]` storage cleanup and retention operations are partially documented through the backup/recovery runbook

Exit condition:

- backups are scheduled in target environments, restoration has been tested in a clean drill, and critical failures have a written recovery path

### 5. Performance and Capacity

- `[~]` product works functionally, but no formal production performance gate has been cleared
- `[ ]` slow-query review has been completed
- `[ ]` index review has been completed for high-volume tables
- `[ ]` queue throughput has been evaluated
- `[ ]` export/report generation has been tested under realistic volume
- `[ ]` webhook fan-out behavior has been tested under burst conditions
- `[ ]` representative load test has been run

Exit condition:

- major workflows have known acceptable response and throughput behavior under expected load

### 6. Security and Governance

- `[x]` role-based authorization and company scoping are enforced
- `[x]` finance and approval workflows have baseline control structure
- `[~]` attachment support exists but hardening is incomplete
- `[ ]` attachment virus scanning exists
- `[ ]` strict MIME allowlists by module exist
- `[ ]` secret rotation/runbook coverage exists for integration credentials
- `[ ]` security review of public API and webhook surfaces is complete
- `[ ]` production environment secret-handling checklist is documented

Exit condition:

- externally exposed surfaces and file handling have explicit hardening controls and operational procedures

### 7. Delivery Process and Release Discipline

- `[x]` CI runs the test suite against PostgreSQL
- `[ ]` nightly regression job exists
- `[ ]` long-running integration job exists
- `[ ]` deployment checklist exists
- `[ ]` rollback procedure exists
- `[ ]` production smoke-test checklist exists

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
   - restore to clean environment
   - run `php artisan ops:recovery:smoke-check`
   - verify app boots and critical workflows function

3. Deployment and rollback runbooks
   - migrate
   - queue restart
   - cache strategy
   - rollback decision points

4. Nightly regression CI
   - full suite
   - build
   - key scheduled jobs or smoke workflow checks

### Phase 4: Performance Gate

1. Query/index review
   - audit high-write and high-read tables
   - verify pagination and sorting paths
   - inspect reporting/export-heavy queries

2. Load and burst testing
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

1. clean-environment restore drills
2. performance/index review and load testing
3. nightly regression CI
4. deployment and rollback runbooks
5. queue retry and poison-message handling policy

## Ownership Rule

When evaluating future work, ask:

- does this make the product more feature-rich, or more operable?

Until the checklist above is closed, prefer operability and hardening work over new module expansion.
