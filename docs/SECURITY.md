# Security model and verification

Security is a maintained process. No application can be guaranteed secure against every attack. This MVP includes concrete controls and regression tests; it has not undergone an independent penetration test or compliance certification.

## Implemented controls

| Threat / concern | Control |
| --- | --- |
| Unauthorized actions | Session authentication and per-route RBAC middleware; permissions checked again for cancellation and cross-order refunds |
| Disabled/temporary accounts | Active account checked each request; temporary-password accounts limited to password change and sign-out |
| Credential attacks | Bcrypt password hashes; strong new-password rules; 72-byte maximum to avoid bcrypt truncation; login and sensitive-route throttles |
| Session fixation / reuse | Session ID regeneration on login/password change; destruction on logout; other sessions revoked after password change; staff changes revoke sessions |
| CSRF | Laravel web middleware tokens for all mutations, no exemptions |
| SQL injection | Eloquent/query-builder bindings; fixed reporting SQL; validated route/filter fields; no dynamic user-supplied SQL |
| Stored/reflected XSS | Blade escaping, no raw rendering of user-supplied content, strict same-origin CSP, no inline scripts/event handlers |
| Clickjacking | frame-ancestors none and X-Frame-Options DENY |
| File attacks | Raster-only logo validation, size/dimension limits, image decode/re-encode, random PNG filename, private storage, fixed file-serving route |
| Payment manipulation | Catalog-derived totals, integer minor units, server rounding, transaction/row locks, overpayment rejection, unique submission keys |
| Duplicate refunds | One full refund per payment enforced by both server logic and a database uniqueness constraint |
| Inventory races | Supply row lock, exact decimal arithmetic, no negative stock |
| CSV formula injection | Formula-triggering strings prefixed with an apostrophe in exports |
| Historical tampering through UI | Immutable order items/payment entries, separate refunds, retained stage events and audit entries |
| Sensitive response caching | Authenticated responses use private/no-store cache headers |
| Information disclosure | Debug disabled by default, generic production errors, password/remember token excluded from user JSON |

Additional response headers include nosniff, same-origin referrer policy, and disabled camera/microphone/geolocation capabilities. HTTPS responses add HSTS. Uploaded logos are intentionally public branding, not private attachments.

## Permission boundaries

The permission map is `User::PERMISSIONS`; routes declare the required permission. UI links are also hidden for unavailable actions, but hiding links is not the security boundary. There is no public registration, no customer-accessible order lookup, and no unauthenticated financial API. The read-only JSON API uses the same sessions and permissions as the UI.

Trusted operators can see all orders and their customer contacts/garment notes. All roles can see the dashboard's aggregate collections/customer counts. This is a single-business staff trust boundary, not multi-tenant isolation. Change and test the map if your privacy model requires narrower access.

## Tests

The feature suite exercises guest access, read and mutation role denials, inactive accounts, mandatory password changes, login throttling, actual CSRF rejection, escaped output, security headers, logo rejection/re-encoding, totals independent of submitted prices, exact fractional rounding, immutable catalog snapshots, duplicate payments, overpayments, invalid stages, payment-before-pickup, full refunds, duplicate/cross-order refunds, cancellation rules, stock underflow, administrator preservation, report exports, and CSV formula escaping.

The suite runs on SQLite in memory by default and can run on the isolated `laundry_shop_test` MySQL database. The MySQL run validates the schema and workflow against the target database engine. It does not prove concurrency behavior under load or replace security review. Composer audit checks known advisories, not all possible vulnerabilities.

## Deployment responsibilities and limits

- Use HTTPS, secure cookies, a private MySQL host, least-privilege credentials, and a web root of `public/`.
- Protect `.env`, database dumps, filesystem backups, and the application key. Do not commit credentials.
- Keep Laravel, PHP, MySQL, Composer dependencies, and the operating system patched.
- No MFA, CAPTCHA, SSO, public API tokens, WAF, automatic intrusion detection, or automated encrypted backups are bundled.
- Audit logs are application-protected records, not a tamper-evident external ledger. A privileged database/server operator can alter them.
- Maintain appropriate privacy, retention, customer consent, financial reconciliation, and local tax/receipt policies. This code does not claim legal/regulatory compliance for an unspecified jurisdiction.
- Refunds and financial records reflect staff-entered transactions; funds are handled externally. Do not store card data.

For the framework authentication/session behavior used here, see the [Laravel 12 authentication documentation](https://laravel.com/docs/12.x/authentication). The implemented middleware, service code, and tests are the authoritative description of this application's controls.

Report suspected vulnerabilities privately to the shop's system owner. Include the affected route, reproduction steps, role used, observed impact, and sanitized evidence; do not include production passwords or customer records in public reports.
