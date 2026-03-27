# Secret Handling And Rotation

As of `2026-03-27`, Port-101 has webhook secret rotation history and hardened public-surface controls. What remains before production is environment-level secret governance: where secrets live, who can rotate them, how rotation is verified, and how incidents are handled.

This document is the operational checklist for that gap.

## Secrets In Scope

At minimum, review:

- `APP_KEY`
- database credentials
- mail credentials
- queue/backing service credentials
- webhook endpoint signing secrets
- third-party API credentials
- malware-scan driver credentials or binary access configuration
- storage credentials if the app moves away from the local/private-disk baseline

## Storage Rules

- do not commit secrets to the repository
- do not store production secrets only in `.env` files on developer machines
- keep production secrets in the platform secret store or equivalent managed mechanism
- restrict write access to operators who actually perform rotations
- restrict read access to the minimum role set

## Rotation Checklist

For each secret class, define:

- owner
- rotation cadence
- rotation trigger on incident
- verification steps after rotation
- rollback plan if the new credential fails

## Port-101 Specific Items

### Webhook signing secrets

- endpoint secret rotation is supported in-app and via API
- rotation history is persisted
- subscribers must validate:
  - `X-Port101-Signature`
  - `X-Port101-Signature-Version`
  - `X-Port101-Timestamp`
- replay-window expectations must be enforced by receivers

### API and integration credentials

- personal access tokens used for integrations should be owned by dedicated integration users where possible
- integration tokens should be named and attributable
- unused tokens should be revoked
- staging and production tokens must be different

### Malware scanning

- if the `basic` scan driver is replaced in production, document:
  - driver type
  - host/binary dependency
  - timeout
  - failure mode
  - alert path when the scanner is unavailable

## Verification Checklist

Mark each item with date, owner, and evidence link.

- production secrets are stored in the chosen secret manager or managed deployment mechanism
- no production secret depends on a developer workstation copy
- webhook secret rotation was exercised at least once in staging
- API tokens used for automation are documented and attributable
- compromised-token revocation path is documented
- malware scanning dependency is documented for the chosen environment
- recovery operators know which secrets are required during restore and deploy

## Incident Response Minimums

For secret compromise or suspicion of compromise:

- identify the affected secret
- rotate it immediately
- invalidate old dependent sessions/tokens where applicable
- verify the dependent integration or subsystem recovers
- record the rotation and the operator

## Sign-off Questions

Do not mark secret handling complete until all answers are `yes`.

- Is every production secret class owned?
- Is every production secret stored in an approved location?
- Is rotation cadence defined?
- Is emergency rotation documented?
- Is verification after rotation documented?
- Are staging and production credentials separated?
- Are webhook receiver expectations documented for integrators?
