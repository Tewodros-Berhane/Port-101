# Backup And Recovery

_Last updated: 2026-03-24_

## Purpose

Define the minimum operational backup and restore procedure for Port-101.

This runbook covers:

- PostgreSQL database backups
- local storage backups for `storage/app/private` and `storage/app/public`
- restore validation with `php artisan ops:recovery:smoke-check`

This runbook does not replace infrastructure-level snapshots, managed database point-in-time recovery, or secret-management procedures. Those should still exist in production.

## Backup Scope

Back up the following runtime data:

1. PostgreSQL database
2. `storage/app/private`
   - attachments
   - report exports
   - other private disk assets
3. `storage/app/public`
   - any public-file assets stored through Laravel

Do not rely on application source code backups for runtime recovery. Source code should be restored from Git and deployed separately.

Secrets are out of scope for these scripts:

- `.env`
- cloud credentials
- SMTP credentials
- webhook secrets managed outside the database

Those must be managed by your deployment platform or secret manager.

## Default Backup Directories

Configured in `config/core.php`:

- database dumps: `storage/app/backups/database`
- storage archives: `storage/app/backups/storage`
- default retention: `14` days

## Minimum Backup Cadence

Recommended minimum baseline:

1. PostgreSQL logical dump every 6 hours
2. PostgreSQL logical dump immediately before every production deployment
3. Storage archive daily
4. Storage archive immediately before every production deployment
5. Restore drill at least monthly and after major infrastructure changes

Recommended retention baseline:

1. 14 daily backups
2. 8 weekly backups
3. 6 monthly backups

If you use managed PostgreSQL with WAL/PITR, keep that enabled in addition to the logical dump workflow here.

## Scripts

Cross-platform backup scripts live under `scripts/ops`.

### PostgreSQL

- Linux/macOS:
  - `scripts/ops/backup-postgres.sh`
  - `scripts/ops/restore-postgres.sh`
- Windows PowerShell:
  - `scripts/ops/backup-postgres.ps1`
  - `scripts/ops/restore-postgres.ps1`

### Storage

- Linux/macOS:
  - `scripts/ops/backup-storage.sh`
  - `scripts/ops/restore-storage.sh`
- Windows PowerShell:
  - `scripts/ops/backup-storage.ps1`
  - `scripts/ops/restore-storage.ps1`

## Prerequisites

### PostgreSQL tools

The database scripts require:

- `pg_dump`
- `pg_restore`

These must be installed and available on `PATH`.

### Storage tools

- Linux/macOS scripts use `tar`
- PowerShell scripts use `Compress-Archive` and `Expand-Archive`

## Running Backups

### Linux/macOS

Database dump:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=port_101
export DB_USER=postgres
export DB_PASSWORD=your-password

./scripts/ops/backup-postgres.sh
```

Storage archive:

```bash
./scripts/ops/backup-storage.sh
```

### Windows PowerShell

Database dump:

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "5432"
$env:DB_DATABASE = "port_101"
$env:DB_USERNAME = "postgres"
$env:DB_PASSWORD = "your-password"

.\scripts\ops\backup-postgres.ps1
```

Storage archive:

```powershell
.\scripts\ops\backup-storage.ps1
```

## Running Restores

Restore should be done into:

- a clean recovery environment first
- then production only after validation is complete

### Linux/macOS

Database restore:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=port_101
export DB_USER=postgres
export DB_PASSWORD=your-password

./scripts/ops/restore-postgres.sh storage/app/backups/database/port_101-YYYYMMDD-HHMMSS.dump
```

Storage restore:

```bash
./scripts/ops/restore-storage.sh storage/app/backups/storage/port-101-storage-YYYYMMDD-HHMMSS.tar.gz
```

### Windows PowerShell

Database restore:

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "5432"
$env:DB_DATABASE = "port_101"
$env:DB_USERNAME = "postgres"
$env:DB_PASSWORD = "your-password"

.\scripts\ops\restore-postgres.ps1 -InputFile .\storage\app\backups\database\port_101-YYYYMMDD-HHMMSS.dump
```

Storage restore:

```powershell
.\scripts\ops\restore-storage.ps1 -InputFile .\storage\app\backups\storage\port-101-storage-YYYYMMDD-HHMMSS.zip
```

## Restore Procedure

Use this order.

1. Put the application into maintenance mode or remove it from traffic.
2. Stop queue workers and scheduler on the target environment.
3. Restore the PostgreSQL dump into the target database.
4. Restore the storage archive into the application root.
5. Clear cached state:

```powershell
php artisan optimize:clear
```

6. Ensure schema is current:

```powershell
php artisan migrate --force
```

7. Rebuild the public storage link if needed:

```powershell
php artisan storage:link
```

8. Run the recovery smoke check:

```powershell
php artisan ops:recovery:smoke-check
```

9. Resume queue workers and scheduler.
10. Run the manual verification checklist below.

## Recovery Smoke Check

`php artisan ops:recovery:smoke-check` verifies:

1. database connection works
2. no pending migrations remain
3. the configured attachments disk can write, read, and delete a temp file
4. backup output directories exist and are writable
5. critical tables exist:
   - `migrations`
   - `jobs`
   - `failed_jobs`
   - `audit_logs`
   - `attachments`

Machine-readable output:

```powershell
php artisan ops:recovery:smoke-check --json
```

## Manual Verification Checklist

After restore and smoke-check, verify:

1. login works
2. company dashboard loads
3. platform dashboard loads
4. queue health loads
5. governance page loads
6. a recent attachment can be downloaded
7. a report export can be downloaded or regenerated
8. notifications dropdown loads
9. webhook workspace pages load
10. no new critical alerts appear in queue health or governance after workers resume

## Scheduler And Worker Recovery

After restore:

1. start the scheduler
2. start queue workers
3. force a scheduler heartbeat if needed:

```powershell
php artisan platform:operations:heartbeat
php artisan platform:operations:scan-alerts --force
```

This confirms the alerting layer is healthy after recovery.

## Failure Policy

If any of the following fail, stop and do not reopen traffic:

1. `ops:recovery:smoke-check`
2. migration status
3. attachment disk round-trip
4. queue health page load
5. authentication flow

## Suggested Scheduling

### Linux cron example

```cron
0 */6 * * * cd /var/www/port-101 && ./scripts/ops/backup-postgres.sh
30 1 * * * cd /var/www/port-101 && ./scripts/ops/backup-storage.sh
```

### Windows Task Scheduler

Run:

- `powershell.exe -File C:\path\to\erp\scripts\ops\backup-postgres.ps1`
- `powershell.exe -File C:\path\to\erp\scripts\ops\backup-storage.ps1`

Use the same cadence as above unless stricter tenant requirements apply.

## Current State

Implemented in the repo:

- backup/restore scripts for PostgreSQL and local storage
- documented recovery runbook
- restore smoke-check command

Still required outside the repo:

1. actual scheduler or cron wiring in each deployed environment
2. encrypted off-host backup storage
3. clean-environment restore drill execution and sign-off
