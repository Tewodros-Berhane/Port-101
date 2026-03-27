# Backup Scheduling And Retention

As of `2026-03-27`, Port-101 has backup/restore scripts, restore-drill automation, and restore-signoff tooling. What remains is target-environment verification that those backups are actually scheduled, retained, encrypted, and recoverable on the infrastructure where the app will run.

This checklist is for that last mile. It is not a replacement for `docs/backup-and-recovery.md`.

## Minimum Backup Policy

- database backups run on a defined schedule
- application storage backups run on a defined schedule
- backup artifacts are encrypted at rest
- backup artifacts are stored outside the primary app host
- retention windows are explicit, not ad hoc
- restore ownership is assigned to a person or team

## Required Scheduling Decisions

Define these before production:

- database full-backup cadence
- storage backup cadence
- retention window for daily backups
- retention window for weekly backups
- retention window for monthly backups
- where artifacts are stored
- who receives backup failure alerts

Record the actual values used in the target environment, not just intended defaults.

## Verification Checklist

Mark each item with date, owner, and evidence link.

### Database backups

- backup job exists in the target scheduler
- backup job runs with the correct database name and credentials
- backup artifacts land in the expected storage location
- artifact timestamps match the defined cadence
- retention cleanup removes expired artifacts as expected
- failed backup execution produces an alert

### Storage backups

- storage backup job exists in the target scheduler
- backup includes `storage/app/private`
- backup includes `storage/app/public`
- attachment-related artifacts are included where required
- artifact timestamps match the defined cadence
- retention cleanup removes expired artifacts as expected
- failed backup execution produces an alert

### Artifact handling

- artifact location is not the same host-only disk relied on by the app
- encryption at rest is enabled or guaranteed by the storage platform
- access to backup artifacts is restricted by role
- restore operators know how to retrieve the latest usable artifacts

## Evidence To Retain

For each target environment, retain:

- scheduler or platform-job configuration screenshot/export
- one successful database backup artifact reference
- one successful storage backup artifact reference
- one retention cleanup execution reference
- one restore-signoff artifact from `storage/app/restore-signoffs`
- one smoke-check artifact from `storage/app/restore-drills/<workspace>/logs`

## Sign-off Questions

Do not mark backup scheduling complete until all answers are `yes`.

- Are both database and storage backups scheduled?
- Is retention enforced automatically?
- Are artifacts stored outside the primary host?
- Is alerting in place for backup failures?
- Has a restore been tested against those scheduled artifacts?
- Is the restore evidence retained and linked?

## Port-101 Commands Used In Verification

These commands verify the restore path after scheduled artifacts are produced:

```powershell
.\scripts\ops\run-restore-drill.ps1
.\scripts\ops\record-restore-signoff.ps1 -Workspace <restore-workspace>
```

If you want a disposable seeded source for a local rehearsal without touching the working app DB:

```powershell
.\scripts\ops\run-seeded-restore-signoff.ps1
```
