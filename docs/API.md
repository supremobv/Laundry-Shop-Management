# HTTP and API reference

## Authentication

The app uses same-origin Laravel session cookies, not public API keys. First `GET /login`, submit `POST /login` with `email`, `password`, and the form `_token`, then retain the session cookie. A temporary-password account must complete its password change before using the API. `POST /logout` invalidates the session and regenerates the CSRF token.

Use `Accept: application/json` for JSON responses/errors. Unauthenticated JSON requests return 401. Disallowed roles return 403. Validation errors return 422 with `{ "message": "…", "errors": { "field": ["…"] } }`. Rate limiting returns 429. Never use this API from an untrusted origin or share browser cookies as integration credentials.

## Read-only API v1

All endpoints require an active signed-in account, required password change completed, and are limited to 60 requests per minute. Numeric IDs refer to internal records.

| Method | Path | Permission | Filters |
| --- | --- | --- | --- |
| GET | /api/v1/orders | orders.view | q (max 100 chars), status, overdue=1, page |
| GET | /api/v1/orders/{id} | orders.view | None |
| GET | /api/v1/customers | customers.manage | q (name/phone, max 100 chars), page |

List endpoints return Laravel pagination envelopes with `data`, `current_page`, `last_page`, `per_page`, `total`, and navigation URLs. Orders/customers are paginated at 15 entries. Order detail includes customer, item price snapshots, payments, refunds, stage events, and creator. Monetary storage fields ending in `_cents` are integer minor units. `tax_basis_points` is 100 units per percent. Quantity may be a decimal string.

Example order-list structure (extra standard paginator fields omitted):

```json
{
  "current_page": 1,
  "data": [{
    "id": 42,
    "number": "LF-261024-ABC123",
    "status": "received",
    "priority": "normal",
    "customer_id": 7,
    "subtotal_cents": 16250,
    "discount_cents": 1000,
    "tax_basis_points": 1200,
    "tax_cents": 1830,
    "total_cents": 17080,
    "payments_sum_amount_cents": 5000,
    "refunds_sum_amount_cents": null
  }],
  "per_page": 15,
  "total": 1,
  "last_page": 1
}
```

List aggregates may be numeric strings or null depending on the database driver. Treat a null sum as zero. Net paid is payments minus refunds; for a non-cancelled order, balance is total minus net paid. Cancelled orders owe zero. The API contains operational personal information; store and share responses only under your business's access policy.

Example same-origin JavaScript from an already authenticated page:

```javascript
const response = await fetch('/api/v1/orders?status=ready', {
  headers: { Accept: 'application/json' },
  credentials: 'same-origin'
});
if (!response.ok) throw new Error('Unable to load orders.');
const page = await response.json();
```

## Browser mutation routes

These are the HTML application's form contracts, **not a separately versioned public write API**. All require session authentication and CSRF token `_token` or `X-CSRF-TOKEN`. Successful submissions redirect and flash a message. Error behavior depends on the Accept header. Use POST plus `_method=PUT/PATCH/DELETE` for HTML forms.

| Method | Path | Permission | Main fields |
| --- | --- | --- | --- |
| POST | /orders | orders.create | customer_id, due_at, priority, items[][service_id], items[][quantity], discount, rack, notes |
| PATCH | /orders/{order}/status | orders.process; cancel additionally orders.cancel | status, note (required for cancellation) |
| POST | /orders/{order}/payments | payments.create | amount, method, reference, idempotency_key (UUID) |
| POST | /orders/{order}/payments/{payment}/refund | payments.refund | reason (5–255 chars); full refund only |
| POST / PUT / DELETE | /customers / /customers/{id} | customers.manage | name, phone, optional email/address/notes; DELETE archives |
| POST / PUT | /services / /services/{id} | catalog.manage | name, unit, price, turnaround_hours, description, active |
| POST | /inventory | inventory.manage | name, unit, reorder_level |
| POST | /inventory/{supply}/adjust | inventory.manage | quantity (signed nonzero), reason, reorder_level |
| POST | /expenses | expenses.manage | description, category, amount, spent_on |
| POST / PUT | /staff / /staff/{id} | staff.manage | name, email, role, active, optional reset password + confirmation |
| PUT | /settings | settings.manage | business fields; multipart logo optional |
| PUT | /profile/password | authenticated account | current_password, password, password_confirmation |

Amounts submitted by forms are decimal currency strings with at most 2 decimal places; e.g. `162.50`, not cents. The server chooses service prices and computes all totals. `idempotency_key` must be newly generated for a new payment and preserved for a retry of that same payment. A refund route verifies the payment belongs to the selected order.

GET `/reports/export?from=YYYY-MM-DD&to=YYYY-MM-DD` downloads a CSV with payment and refund entries. Permission: reports.view. The range cannot exceed 366 days.

GET `/business-logo` is intentionally public so branding renders on login. It serves only the configured, re-encoded image or bundled sample logo. Uploaded files are not served by arbitrary filesystem paths.

For complete route names and middleware, run `php artisan route:list --except-vendor`. If building third-party integrations, add scoped credentials, token lifecycle management, explicit data-transfer authorization, and a versioned write API rather than exposing staff session cookies.
