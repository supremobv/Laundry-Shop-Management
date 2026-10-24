# FreshFold user manual

The business name and logo in the interface come from Business Settings. FreshFold is replaceable sample branding. A quick version of this guide is also available inside the application under **Open user guide**.

## Roles and access

| Capability | Administrator | Manager | Cashier | Operator |
| --- | :---: | :---: | :---: | :---: |
| Overview, order details, receipts | Yes | Yes | Yes | Yes |
| Create orders and manage customers | Yes | Yes | Yes | — |
| Update processing stages / confirm pickup | Yes | Yes | Yes | Yes |
| Record payments | Yes | Yes | Yes | — |
| Refund payments / cancel orders | Yes | Yes | — | — |
| Services, supplies, expenses, reports | Yes | Yes | — | Supplies only |
| Staff accounts, branding, audit log | Yes | — | — | — |
| Change own password / read guide | Yes | Yes | Yes | Yes |

The overview includes aggregate shop collections and customer counts for all four roles. Operators can see customer contact information and garment notes on orders so they can process and hand over laundry. Restrict real staff roles to trusted people. Permission definitions are centralized in `app/Models/User.php` for business-specific customization by a developer.

## Sign in and account care

Use your assigned email and password. First login with a temporary password requires setting a personal password. Passwords need at least 12 characters, uppercase and lowercase letters, a number, and a symbol. Click your name at the bottom of the sidebar to change your password later. Sign out using the icon at the upper right when leaving a shared device. Sessions expire after 60 minutes of inactivity by default.

Five failed attempts per email/IP trigger a short login lockout; a second route-level limit reduces repeated requests. Ask an administrator to reset a forgotten password. This MVP uses administrator-assisted reset rather than email password recovery.

## First-time shop setup

An administrator configures Business settings before the first real order. Change the business name, tagline, phone, email, address, logo, currency, timezone, tax, and receipt message. Upload PNG, JPEG, or WebP. Restore the sample logo with the checkbox. New uploads are validated and re-encoded as PNG.

Currency and timezone cannot change once orders exist. Historical service prices and tax rates are saved with each order. Business contact details and branding on printed receipts reflect current settings.

Managers or administrators create the service catalog. A service has a name, price, unit, description, availability, and typical turnaround hours. Turnaround is a planning reference; each order's actual pickup time is selected at intake. Choose units from kg, piece, load, and pair. Disable a service to stop new sales; old orders retain its name and rate.

## Receive an order

1. In Customers, search by name or phone before adding someone. A phone number is required. Email, address, and preferences are optional. Avoid collecting unnecessary personal information.
2. Click New order. Select the customer and a future pickup time in your business timezone.
3. Choose Normal or Express. Express is a priority label. Add the separate express surcharge service if you charge extra.
4. Record a rack/bag label and garment information such as piece counts, stains, damage, pockets checked, and care instructions.
5. Select services and enter quantities with up to two decimal places. Add or remove service rows as needed, up to 20 lines per order.
6. Enter an optional fixed discount. Tax applies after discount. Review the estimate and click Create order. The server recalculates the price from the catalog.
7. Record a deposit or full payment on the order details page if the customer has paid.
8. Open Receipt / print to print a claim receipt or save a PDF through the browser's print dialog.

The maximum subtotal per order is 10 million currency units. Split unusually large orders. Order line items are immutable after creation to keep the price and payment history reliable. For a wrong unpaid order, cancel it and create a corrected order.

## Process and hand over laundry

Use Orders to search by order number, customer, or phone. Filter by stage or Overdue only. Overview shows the active workflow and earliest pickup deadlines. Ready orders are not marked overdue because they are already awaiting customer collection.

Advance through **Received → Washing → Drying → Folding → Ready for pickup → Completed**. You cannot skip stages or reopen an order through the normal process. Add a handover note when useful. For specialist services that do not physically use every stage, advance through unused stages with an explanatory note. The system records each transition, user, timestamp, and note.

Before marking Completed, verify the garments were handed to the customer. The system requires the full balance to be paid. Operators may confirm pickup after a cashier has recorded the payment. There is no automated customer notification in this MVP; contact the customer using your shop's normal method when ready.

## Payments and refunds

Payments record funds already received outside the application. Supported methods are cash, card, bank transfer, and e-wallet. This application does not process a card or move money. Do not enter card numbers, CVVs, or banking passwords. Store only the transaction reference needed for reconciliation.

Partial deposits are supported. The system rejects zero, negative, or excess payments. A payment form has a unique submission key to prevent a repeated submission from recording the same payment twice. Before entering a new payment after an error or refresh, review Payment history to ensure it has not already been recorded.

Managers and administrators can use Refunds & corrections on the order page:

1. Verify the original payment and return the money externally.
2. Select the corresponding payment, enter a reason, and record its full refund.
3. The original payment remains; a separate refund entry reduces net paid. A payment can only be refunded once.
4. For cancellation, refund every payment, then Cancel order with a reason. An unpaid unfinished order can be cancelled directly. A completed order can only be cancelled after its payments have been fully refunded.

Refunds reverse a complete payment entry. Partial refunds of a single payment, chargeback handling, and payment-gateway automation are outside this MVP. If an incorrect payment was recorded, refund that entry with a clear correction reason and record the correct payment. Refund history and audit history preserve the correction.

## Customers

Customer profiles contain contact details, preferences, and paginated order history. Edit information as needed. Archive customers with no unfinished orders to remove them from new-order selection. Historical orders retain the customer relation. Archiving is not a privacy erasure operation; retention and erasure must follow your business's applicable policy. To restore an archived customer, select Archived profiles in Customers, search, and click Restore profile.

## Supplies

Add a supply name, unit, and reorder threshold. New supplies start at zero. Use Adjust stock for deliveries (positive number), opening stock (positive), or consumption (negative), with a reason. Set or update the reorder level on the same form. On-hand stock cannot go below zero. Recent movements show staff, timestamps, quantities, and reasons. Consumption is manual; creating or completing orders does not deduct detergent automatically.

## Expenses and reports

Record operating expenses by description, date, category, and amount. The MVP keeps expenses append-only for traceability; corrections requiring ledger modification need administrator reconciliation and an auditable maintenance process.

Reports accepts up to 366 days at a time and displays:

- **Gross collections:** payment entries received within the selected date range.
- **Expenses:** spending dated within the range.
- **Refunds:** funds returned within the range, even if the payment was received earlier.
- **Net cash movement:** collections less refunds and expenses; this is not accounting profit.
- **Booked order value:** non-cancelled orders created in the range, regardless of payment date.
- **Outstanding:** all-time non-cancelled order totals less net payments, independent of the date range.

Export payments downloads a CSV including payments and refund entries for reconciliation. Refunds have an `R-` ID and a negative amount. Formula-like CSV values are escaped for spreadsheet safety; spreadsheet software may initially treat negative refund amounts as text. The refund table shows the latest 100 entries in the period; the export includes all matching entries. The dashboard weekly chart shows gross payments; today's collections card is net of today's refunds.

## Staff and audit history

Administrators create accounts, assign one of four roles, reset passwords, and deactivate accounts. Share temporary passwords privately. Account changes revoke prior browser sessions. A password change also revokes the user's other sessions. Users cannot remove their own administrator access, and at least one active administrator is required. Accounts with financial history are deactivated rather than deleted.

Audit log is an administrator-only, read-only activity log. It records important authentication, customer, catalog, order, payment, refund, stock, expense, staff, settings, and export events. Audit entries are protected by application permissions but are not cryptographically tamper-proof against a database administrator.

## When something goes wrong

Validation errors explain what to correct and normally preserve form text. Passwords and file uploads must be entered again. An expired form session requires refresh or sign-in. Permission errors require a role change by an administrator. For generic server errors, contact your administrator; do not repeatedly submit payments without checking history. The technical recovery guide is `docs/OPERATIONS.md`.
