# Queue Poison-Message Handling

_Last updated: 2026-03-25_

## Purpose

Define how Port-101 operators should handle failed queue jobs that are unlikely to succeed on replay.

This document covers:

- replay versus discard decision rules
- poison-message heuristics surfaced in platform queue health
- operator actions in the platform workspace
- minimum audit and runbook expectations

This policy exists to stop repeated replay of non-retryable failures from turning into hidden backlog churn.

## Definitions

### Retryable failure

A failure that is plausibly transient, for example:

- temporary network or upstream timeout
- worker restart during execution
- external service returned a temporary `5xx`

### Poison message

A failed queue payload that is unlikely to succeed if replayed again without changing code, data, or configuration.

Typical examples:

- authorization failure
- validation failure
- missing model or bad route target
- repeated identical failure fingerprint across multiple retries

## Current Port-101 Operator Workflow

Use:

- `/platform/operations/queue-health`

This page now exposes:

1. failed-job triage guidance
2. recommended action per failed job
3. explicit `Discard as poison` operator action
4. recent poison-message decisions

The queue-health page still supports:

- `Retry`
- `Forget`

But the intended policy is:

- `Retry` for transient failures
- `Discard as poison` when the same non-retryable failure keeps repeating
- `Forget` only for cleanup when an operator already understands the failure and does not need the poison-review record

## Built-In Triage Rules

Port-101 now marks failed jobs using three levels:

1. `retryable`
2. `investigate`
3. `poison_suspected`

### `retryable`

Used when:

- no poison indicators are present
- the failure fingerprint is not repeating beyond the review threshold

Recommended action:

- `retry`

### `investigate`

Used when:

- the same failure fingerprint appears more than once
- but it has not yet crossed the poison threshold

Recommended action:

- `review_before_retry`

### `poison_suspected`

Used when either of these is true:

1. the exception class is configured as non-retryable
2. the same failure fingerprint reaches the configured repeat threshold

Recommended action:

- `discard_as_poison`

## Non-Retryable Exception Baseline

Port-101 currently treats these as non-retryable by default:

- `Illuminate\Auth\Access\AuthorizationException`
- `Illuminate\Database\Eloquent\ModelNotFoundException`
- `Illuminate\Validation\ValidationException`
- `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`
- `Symfony\Component\HttpKernel\Exception\BadRequestHttpException`

These are configured in:

- `config/core.php`

## Repeat-Failure Threshold

Poison suspicion is also triggered when the same failure fingerprint repeats within the configured review window.

Current defaults:

- review window: `24` hours
- poison threshold: `3` matching failures

The fingerprint uses:

- queue
- job name
- exception class
- company

This keeps the heuristic stable enough for operators without overfitting to exception text noise.

## Operator Decision Rules

Use this order.

### Replay once

Choose `Retry` when:

- the guidance says `retryable`
- the failure looks infrastructure-related
- there is no repeated matching pattern yet

### Review before replay

Pause and inspect when:

- the guidance says `investigate`
- the same job already failed more than once
- a tenant-specific data issue is plausible

Checks to make:

1. open the impacted company if applicable
2. inspect recent audit logs around the failure time
3. check whether a deployment, migration, or configuration change caused the issue
4. check external upstream health if the job depends on a third-party endpoint

### Discard as poison

Choose `Discard as poison` when:

- the guidance says `poison_suspected`
- the exception class is non-retryable
- replay would only create more queue churn
- engineering or operations already understands that the payload is bad

This action:

1. removes the failed job from the retry queue
2. records a poison-message review entry
3. preserves the operator decision trail separately from the failed queue row
4. writes an audit-log entry

## When To Use `Forget`

`Forget` should not be the default poison workflow anymore.

Use `Forget` only when:

- the failure is already documented elsewhere
- the operator intentionally wants queue cleanup without a poison review record
- the record is duplicate noise and not useful for incident history

If the job is truly poison, prefer `Discard as poison`.

## Escalation Path

Escalate to engineering instead of replaying repeatedly when:

1. the same job fingerprint keeps failing after one replay
2. the queue-health page shows growing poison-suspected counts
3. multiple companies are impacted by the same job type
4. the failure followed a deployment or migration
5. the failure involves finance posting, reconciliation, or inventory movement integrity

## Evidence To Retain

For each meaningful poison-message event, retain:

1. failed job id
2. queue name
3. company id if applicable
4. exception class and message
5. operator decision
6. review timestamp
7. reviewer identity

Port-101 now persists this through:

- `queue_failure_reviews`
- audit logs

## Minimum Production Procedure

When queue failures rise materially:

1. open `/platform/operations/queue-health`
2. review `Failed jobs`
3. filter by queue if needed
4. replay only jobs marked `retryable`
5. inspect `investigate` jobs before replay
6. discard `poison_suspected` jobs when replay would be wasteful
7. review `Recent poison decisions`
8. escalate recurring patterns to engineering

## What This Does Not Replace

This policy does not replace:

- fixing the underlying bug
- infrastructure monitoring
- queue worker supervision
- incident management

It only gives operators a controlled way to stop wasteful replay loops and preserve a review trail.
