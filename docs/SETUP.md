# Setup with Laragon and MySQL

This is a local Laravel 12 application backed by a traditional MySQL database. The UI uses Blade, CSS, and a small JavaScript file. There is no React runtime, CDN dependency, or required Node build step. All business data is server-side.

## Requirements

- PHP 8.2 or newer supported by the locked Composer dependencies.
- PHP extensions: PDO, pdo_mysql, mbstring, openssl, tokenizer, XML, ctype, fileinfo, GD, BCMath, and curl. pdo_sqlite is needed only for the default isolated test suite.
- Composer 2; MySQL 8.0+ (verified locally on MySQL 8.4.3); a modern browser.
- Start MySQL from Laragon. The PHP development server can run without starting Apache.

## First installation

Open PowerShell in the project directory:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Do not overwrite an existing `.env` or regenerate its key during an upgrade. Edit the database section of `.env`:

```dotenv
APP_URL=http://127.0.0.1:8000
APP_ENV=local
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laundry_shop_mvp
DB_USERNAME=root
DB_PASSWORD=
```

Laragon often uses root with an empty password locally. Use your actual credentials. Root without a password is only suitable for an isolated local development database. The helper below connects using `.env` and creates only the named database if absent; it does not drop tables:

```powershell
php scripts/check-database.php --create
php artisan migrate
php artisan laundry:install --email=owner@example.com --name="Shop Owner"
php artisan serve --host=127.0.0.1 --port=8000
```

The installer prints a randomly generated temporary owner password **once**. Save it privately. Open http://127.0.0.1:8000, sign in, and set your personal password. Installation refuses to overwrite existing accounts. It adds a starter service catalog; customize it before taking real orders.

For a fictional shop to explore, use this install command **instead of** the normal installer on a new database:

```powershell
php artisan laundry:install --demo --no-interaction
```

Demo owner email: `admin@freshfold.test`. The temporary password is generated, not a fixed password in the source. Demo data contains fictional customers, 18 orders, payments, expenses, and supplies. Demo installation is blocked in production and when customer/order data already exists. All mutations in installation are transactional.

## Business configuration

1. Change the owner password.
2. Open **Business settings**. Set the logo, name, tagline, address, contact details, currency, timezone, tax, and receipt footer.
3. Currency and timezone lock after the first order to protect historical amounts and local timestamps. Configure these on a clean business database before intake. A demo database has orders, so these values are intentionally locked there.
4. Configure **Services & pricing**. Disable services you do not offer.
5. Create individual staff accounts in **Team & access**.
6. Add supply records and opening stock.

To begin real operations after exploring a demo, configure a **new empty database** in `.env`, clear config, migrate, and run `laundry:install` without `--demo`. This preserves the demo database without mixing fictional and real transactions. Do not run destructive reset commands on business records.

## Daily startup

Start Laragon MySQL, then run `php artisan serve --host=127.0.0.1 --port=8000`, or run `scripts/start.ps1`. Keep the terminal open. Stop the server with Ctrl+C. The development server is for localhost testing.

For Laragon Apache/Nginx virtual hosting, point the virtual host document root at the project's **public** directory, set `APP_URL` to the configured host, and ensure rewrite support is enabled. Never serve the repository root: it contains `.env`, backups, and application source.

## Verify

```powershell
php scripts/check-database.php
php artisan test --compact
php artisan view:cache
composer audit
node --check public/js/app.js
```

The default tests use an in-memory SQLite database, not your business database. To run the same workflows on MySQL, the following uses and resets the dedicated **laundry_shop_test** database. Never point this configuration at a real shop database:

```powershell
php scripts/check-database.php --test --create
php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml
```

MySQL test credentials inherit the local `.env` username/password. Edit a local copy of the XML or supply test-only credentials if your database account cannot access the test database. The database name is explicitly forced to `laundry_shop_test` in this test configuration.

## Troubleshooting

| Symptom | Action |
| --- | --- |
| Database connection refused | Start MySQL in Laragon; check host and port. Run `php scripts/check-database.php`. |
| Access denied | Check `.env` DB username/password and database grants. Run `php artisan config:clear` after changes. |
| Missing application key | On a fresh installation only, run `php artisan key:generate`. Preserve existing keys. |
| 419 form/session expired | Refresh or sign in again. Check payment history before retrying financial entries. Use the same host consistently; do not mix localhost and 127.0.0.1. |
| Login redirects to My account | Set your personal password; temporary passwords cannot enter operations. |
| Permission error | Ask an administrator to assign the correct role or reactivate the account. |
| Forgot owner password | With trusted server-console access, run `php artisan laundry:reset-password owner@example.com`. Confirm the prompt, then sign in with the printed temporary password. |
| Logo rejected | Use a real PNG/JPEG/WebP file up to 2 MB and 2048×2048 pixels. SVG uploads are deliberately not accepted. |
| Generic 500 error | Check `storage/logs/laravel.log` privately. Check MySQL availability and write permissions on storage and bootstrap/cache. |
| Port 8000 occupied | Choose a free port in `artisan serve` and update APP_URL. |
| Old config or templates | Run `php artisan optimize:clear`, then restart the server. |

Uploaded logos are served through a safe application route. No `storage:link` is needed.
