# Operations, backup, and recovery

## Local development and production

`artisan serve` and Laragon's default root database account are for local testing. For a real deployment, use a maintained PHP web server configuration that serves only `public/`, HTTPS, a dedicated database user, private database networking, and operating-system file permissions. Set `APP_ENV=production`, `APP_DEBUG=false`, the correct HTTPS APP_URL, `SESSION_SECURE_COOKIE=true`, and retain HTTP-only and SameSite cookies.

Install with `composer install --no-dev --optimize-autoloader`, run migrations under a controlled deployment, then `php artisan optimize`. The synchronous MVP needs no worker or scheduled jobs. If adding asynchronous notifications later, configure and monitor their workers. Keep writable permissions limited to `storage` and `bootstrap/cache`; application code and `.env` should not be writable by the web process.

Use a runtime MySQL user limited to this database and required SELECT/INSERT/UPDATE/DELETE privileges. Run schema migrations with a separate deployment account with DDL privileges. Do not put a MySQL root account on a public-facing web server. No credentials should be committed or published.

## Backup contents

A complete recovery set contains:

1. A consistent MySQL dump of the shop database, including migrations, ledger records, settings, and audit history.
2. `storage/app/private/branding/` for uploaded logos.
3. The exact `.env` and APP_KEY, kept encrypted and separately access-controlled.
4. The matching application source and Composer lock file.

Schedule backups outside this MVP using your normal backup software. Keep an off-device encrypted copy and test restoration periodically. Database backups contain customer contact information, password hashes, and session data; restrict access and apply a retention policy. Logging and backups should never be stored under `public/`.

## Example MySQL backup on Windows

Use Laragon's Terminal so `mysqldump` is on PATH, or supply the full executable path for your selected MySQL version. The database name here is the local example; verify your actual DB_DATABASE first. The `-p` option prompts for a password instead of putting it in shell history.

```powershell
New-Item -ItemType Directory -Path C:\LaundryBackups -Force
mysqldump --host=127.0.0.1 --port=3306 --user=YOUR_BACKUP_USER -p --single-transaction --routines --triggers --no-tablespaces --result-file=C:\LaundryBackups\laundry.sql laundry_shop_mvp
```

Use `--result-file` instead of PowerShell output redirection to avoid accidental SQL encoding changes. Replace the static output filename with your dated backup naming policy so earlier backups are retained. Copy branding and the protected environment/key into the encrypted recovery set. MySQL's online documentation describes privileges required by the chosen dump options; use the least privilege required for your server configuration.

## Restore drill

Restore to a new empty recovery database first. Do not overwrite the active shop to test a backup. In Laragon's MySQL console or a trusted database client:

```sql
CREATE DATABASE laundry_recovery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_recovery;
SOURCE C:/LaundryBackups/laundry.sql;
```

Point an isolated app checkout at `laundry_recovery`, restore the matching APP_KEY and branding files, and run `php artisan optimize:clear`. Keep this checkout inaccessible to regular staff while testing. Verify customer/order counts, order totals, payment/refund reconciliation, receipt rendering, account access, and recent audit events. Delete restored sessions or otherwise revoke them before bringing a recovery system into service.

For actual disaster recovery, preserve the failing system and incident evidence, decide the acceptable recovery point, stop writes using maintenance mode (`php artisan down`), back up the current state if possible, restore the approved recovery set, validate it, then re-enable access (`php artisan up`). Document transactions that occurred after the backup and reconcile them before reopening. Restoring an old backup can lose newer transactions.

## Upgrades

1. Back up the database, branding, `.env`, and APP_KEY.
2. Review release changes and test them against a restored copy.
3. Put the application into maintenance mode during incompatible schema changes.
4. Deploy the matching source/lock file; run Composer install and `php artisan migrate --force` with the deployment account.
5. Run the relevant tests in a separate test database, cache configuration/views, and smoke-test login, order intake, payment, and receipt.
6. Reopen the application after checks. Never run `migrate:fresh`, `db:wipe`, or demo seeds against real business data.

## Account recovery

Use Team & access for routine resets. For a locked-out owner, a trusted server operator can run:

```powershell
php artisan laundry:reset-password owner@example.com
```

Confirm the command prompt. It generates a temporary password, flags the account for a mandatory change, revokes database sessions, and records a console audit entry. Share the temporary password privately. It does not reactivate an intentionally inactive account or grant a new role.

## Monitoring and incidents

- Inspect `storage/logs/laravel.log` privately when a generic error occurs. Avoid enabling debug output in a publicly reachable environment.
- Reconcile payments, refunds, and expenses daily using Reports and CSV exports.
- Check low-stock alerts, late orders, inactive staff, disk space, backups, and certificate expiry.
- Run `composer audit` after dependency changes and regularly under your maintenance process. Update dependencies in a test checkout before production rollout.
- After suspected credential theft, deactivate affected accounts, rotate passwords and relevant infrastructure secrets, revoke sessions, review audit/database access logs, and investigate before reactivation.
- APP_KEY rotation is a migration operation, not a routine troubleshooting step; replacing it can invalidate encrypted sessions and data.

## Performance

Main ledgers are paginated, relationships used by tables are eager-loaded, customer intake search is capped at 100 matches, and financial/status/date filters have supporting indexes. The business settings object is reused within each request. Local CSS/JS and system fonts eliminate runtime CDN requests and frontend build overhead. CSV output streams records in chunks.

No load-test or latency guarantee is claimed. Establish realistic transaction volumes and hosting capacity before production. For very large customer catalogs, add full-text/prefix search rather than unbounded contains-search. For large financial ledgers, add reconciled reporting snapshots rather than changing historical money records. Do not cache account permissions across requests without revocation support.
