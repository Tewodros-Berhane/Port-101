# Deployment And Rollback

_Last updated: 2026-03-25_

## Purpose

Define the minimum release procedure for deploying Port-101 safely and rolling back when a release is not healthy.

This runbook covers:

- pre-deploy checks
- deployment sequence
- post-deploy smoke testing
- rollback decision points
- rollback procedure

This runbook assumes:

- backups are taken before deploy
- the target environment already has PHP, Node assets, PostgreSQL access, queue workers, and scheduler wiring
- secrets are managed outside the repo

## Required Inputs Before Deployment

Have these ready before starting:

1. release identifier or commit SHA
2. confirmed backup timestamp for database and storage
3. maintenance window or deployment approval if required
4. rollback target release identifier
5. operator with access to:
   - application host
   - database
   - queue worker control
   - scheduler control

## Pre-Deploy Checklist

Do not start deployment until all of the following are true:

1. current branch/build is the intended release artifact
2. `php artisan test` is green on the release candidate
3. latest database backup exists
4. latest storage backup exists
5. no unresolved critical queue-health incidents are open
6. no unresolved failed migrations from a prior release exist
7. rollback target is identified

## Deployment Sequence

Use this order.

### 1. Take a pre-deploy backup

Database:

```powershell
.\scripts\ops\backup-postgres.ps1
```

Storage:

```powershell
.\scripts\ops\backup-storage.ps1
```

### 2. Put the application into maintenance mode

```powershell
php artisan down --render="errors::503"
```

### 3. Stop or drain queue workers

Use your process manager or runtime supervisor.

If queue workers are managed by Laravel only:

```powershell
php artisan queue:restart
```

### 4. Deploy the new release artifact

At minimum:

1. update source code to the target release
2. install PHP dependencies
3. install/build frontend assets if your deployment artifact does not already contain them

Typical commands:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

If assets are prebuilt in CI, use the artifact instead of building in place.

### 5. Run database migrations

```powershell
php artisan migrate --force
```

### 6. Refresh caches

```powershell
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If route caching or view caching is not part of your target environment strategy, document that exception and keep it consistent.

### 7. Bring the application back up

```powershell
php artisan up
```

### 8. Restart workers and scheduler

Restart your process manager or worker runtime.

Then record a scheduler heartbeat and force an operational scan:

```powershell
php artisan platform:operations:heartbeat
php artisan platform:operations:scan-alerts --force
```

### 9. Run post-deploy smoke checks

Command-based smoke check:

```powershell
.\scripts\ops\deploy-smoke-check.ps1 -RequireHeartbeat
```

Direct Artisan equivalent:

```powershell
php artisan ops:deploy:smoke-check --require-heartbeat
```

### 10. Run the manual smoke-test checklist

See the checklist below. Do not declare the release healthy until both the command and manual checks pass.

## Post-Deploy Smoke-Test Checklist

### Command-Based Checks

The deploy smoke-check validates:

1. app key is configured
2. database connection works
3. no pending migrations remain
4. critical routes are registered
5. queue infrastructure tables exist
6. at least one platform admin exists
7. at least one active company exists
8. scheduler heartbeat is fresh when `--require-heartbeat` is used

Run:

```powershell
php artisan ops:deploy:smoke-check --require-heartbeat
```

JSON output:

```powershell
php artisan ops:deploy:smoke-check --json --require-heartbeat
```

### Manual Checks

Verify these before closing the deploy:

1. login page loads
2. superadmin login works
3. platform dashboard loads
4. platform queue health loads
5. company user login works
6. company dashboard loads
7. one module page from each critical module loads:
   - sales
   - inventory
   - accounting
   - projects
8. notifications dropdown loads
9. webhook workspace loads
10. report export creation works
11. no new critical operational alerts are raised after the deploy

## Rollback Decision Points

Rollback immediately if any of the following occur:

1. migration failure
2. `ops:deploy:smoke-check --require-heartbeat` fails
3. authentication fails
4. platform dashboard or company dashboard does not load
5. queue workers cannot resume cleanly
6. critical background operations fail after deploy:
   - report exports
   - webhook delivery
   - recurring billing
   - inventory reorder scan

Do not try to push through a release that breaks schema, auth, or core operator surfaces.

## Rollback Procedure

Use this order.

### 1. Put the application into maintenance mode

```powershell
php artisan down --render="errors::503"
```

### 2. Stop queue workers

Use your process manager or:

```powershell
php artisan queue:restart
```

### 3. Return code to the last known good release

Redeploy the prior release artifact or revert to the previous release directory.

### 4. Restore database if required

Only restore the database when the release introduced irreversible schema/data problems or failed migrations that cannot be corrected safely in place.

```powershell
.\scripts\ops\restore-postgres.ps1 -InputFile .\storage\app\backups\database\port_101-YYYYMMDD-HHMMSS.dump
```

### 5. Restore storage if required

```powershell
.\scripts\ops\restore-storage.ps1 -InputFile .\storage\app\backups\storage\port-101-storage-YYYYMMDD-HHMMSS.zip
```

### 6. Rebuild runtime state

```powershell
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### 7. Restart workers and scheduler

```powershell
php artisan platform:operations:heartbeat
php artisan platform:operations:scan-alerts --force
```

### 8. Validate rollback health

Run:

```powershell
php artisan ops:deploy:smoke-check --require-heartbeat
php artisan ops:recovery:smoke-check
```

Then rerun the manual smoke-test checklist.

### 9. Reopen traffic

```powershell
php artisan up
```

Only reopen traffic after the rollback release is healthy.

## Release Sign-Off

Before marking a deployment complete, record:

1. deployed release identifier
2. deployment start and finish time
3. backup timestamps used for safety
4. smoke-check result
5. whether rollback was required
6. any follow-up incidents or action items

## Current State

Implemented in the repo:

- `ops:deploy:smoke-check`
- cross-platform deploy smoke-check wrapper scripts
- deployment and rollback runbook
- production smoke-test checklist

Still required outside the repo:

1. environment-specific process-manager commands if you do not use the Laravel defaults above
2. actual clean-environment restore drill sign-off
3. performance/load validation before broad production rollout
