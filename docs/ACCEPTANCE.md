# MVP acceptance checklist

Verified locally on Windows with PHP 8.2.28, Laravel 12.70.1, and Laragon MySQL 8.4.3. See the terminal output for the exact current test run.

Final verification: **25 tests / 219 assertions passed on both SQLite and MySQL**. Blade compilation, JavaScript syntax, Composer manifest validation, and dependency audit passed. Composer reported no known vulnerability advisories. Browser screenshots of the overview, sign-in, order detail, and receipt are saved in `docs/screenshots/`.

| Requirement | Delivered | Evidence |
| --- | --- | --- |
| Traditional MySQL / Laragon | MySQL environment template, connection helper, migrations | Local connection/migrations and isolated MySQL test run |
| Configurable business | Name, logo, tagline, contacts, address, currency/timezone before orders, tax, receipt footer | Settings tests and admin UI |
| Role-based access | Administrator, manager, cashier, operator; central permission map | Read/mutation permission tests |
| Staff security | No public registration; strong passwords; initial password change; deactivation/revocation | Authentication/account tests |
| Customers | Create, search, edit, order history, archive/restore | Feature tests and UI |
| Services | Create/edit/disable, unit prices, turnaround reference | Catalog UI and price snapshot test |
| Order intake | Customer, services/quantities, pickup, priority, rack, notes, discount, tax | Financial validation/render tests |
| Processing | Fixed workflow, staff history, notes, overdue filtering | Transition and payment-before-pickup tests |
| Payments | Deposits/full payments, methods/references, no overpayments, duplicate protection | Payment tests |
| Refunds/cancellations | Full payment reversal, reason, retained history, unpaid cancellation | Refund/ownership/duplicate/cancellation tests |
| Receipts | Branded print and browser save to PDF | Receipt render test and print stylesheet |
| Supplies | Supplies, thresholds, signed stock movements, no underflow | Inventory tests and UI |
| Expenses | Operating expense ledger, dates/categories | UI and report rendering |
| Reporting | Range, collections, refunds, expenses, net cash, outstanding, booked value, CSV | Report/export tests |
| Audit | Protected important activity history | Route permissions and mutation records |
| Modern interface | Responsive navigation, overview, clear forms, empty states, friendly errors, keyboard focus | Browser review of representative pages |
| API | Versioned read-only session JSON endpoints | API tests and API.md |
| Documentation | Setup, manual, architecture, API, security, recovery, scope | docs/ and in-app guide |

Browser review checks representative pages and dynamic intake estimates. Automated rendering covers the remaining main pages. No cross-browser/mobile-device lab, sustained load test, independent penetration test, or jurisdiction-specific compliance certification was performed. Scope and extension points are in ARCHITECTURE.md. Tests cover meaningful workflows, not every possible input or attack.

## Reproduce verification

```powershell
composer test
php scripts/check-database.php --test --create
php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml
php artisan view:cache
composer audit
npm run check
```

MySQL tests reset only the dedicated `laundry_shop_test` database in phpunit.mysql.xml. Never change its target to the live shop.
