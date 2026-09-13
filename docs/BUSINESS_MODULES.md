# Contact, newsletter and commerce modules

## Enable on an existing Laravel installation

1. Back up MySQL and storage, then run `php artisan migrate` from `backend/`. Do **not** use `migrate:fresh` against existing data.
2. Configure `MAIL_MAILER=smtp`, your mail-server settings and `MAIL_FROM_ADDRESS` in the backend environment. Set `FRONTEND_URL` to the browser origin serving Vue. Run `php artisan config:clear` after changes (re-cache configuration for deployment).
3. Grant the new `submissions` and `commerce` capabilities using **Users & roles** and the website **Team** editor. Existing wildcard administrators have access automatically. These modules require no per-site installation or sample-product seeding.
4. Publish both the website and relevant pages. Add **Contact** and/or **Newsletter** blocks in the page builder. The public header/mobile navigation includes **Shop**.

### Contact and newsletter

- Contact forms require a name, valid email, message, consent and an empty honeypot. Laravel applies request throttling. Messages are stored, not forwarded automatically.
- **Inbox** lists contact messages and newsletter records with pagination. Mark messages read/archive, or permanently delete personal data. Customer data is never included in the public page/catalog APIs.
- Newsletter signup sends a verification email through Laravel Mail. The token is hashed in MySQL, expires after 24 hours, and is consumed atomically once. A pending record is **not** a confirmed subscriber.
- The verification page offers **Confirm subscription** and **Unsubscribe**. To manage an existing subscription after its link expires, submit the same email in the public form to receive a new management link. Existing confirmed status is retained until the mailbox owner chooses an action.
- The production environment rejects the `log` and `array` mail drivers instead of claiming real delivery. SMTP acceptance is not a guarantee of inbox delivery; monitor the mail provider and configure SPF/DKIM/DMARC.
- Local Node preview intentionally does **not** send mail. Its Inbox exposes a clearly labeled **Preview verification email** link to the demo administrator. Laravel never returns this link or the token in the inbox/public signup response.
- Campaign composition, bulk sending, delivery/bounce tracking, and automatic contact replies are not included in this submission workflow.

### Store and orders

- **Products** supports create/edit, site-scoped unique SKUs, descriptions, image URLs, currency, integer-minor-unit prices, stock and active/archive status. Version checks reject stale edits—including edits made before another customer purchased stock.
- This release supports the website's **base currency**, with two decimal places (the seven seeded currencies all use two). Changing a website currency does not silently convert product prices: reprice products in the new currency. Products in another currency are omitted from the catalog.
- The public **Shop** has a paginated catalog, session-local cart and checkout. Prices include all charges and delivery is free in this baseline; there is no tax or shipping-rate engine. Quantities are bounded. Customer details and consent are validated.
- Checkout ignores client prices, uses server-side product prices, locks the website/products in a MySQL transaction, and reserves inventory. Failed checkout rolls back all changes. A unique per-site idempotency key prevents duplicate orders/reservations on retries. Reusing a key with different checkout details returns 409.
- An order snapshots product name/SKU/unit price/quantity/currency. Editing a product cannot change an existing order.
- **Orders** shows customer delivery details and line items. Allowed transitions are pending → confirmed → fulfilled, or pending/confirmed → cancelled. Cancelling restores stock once. Version-by-expected-state checks reject stale administrative actions.
- Payment method is **cash on delivery**. Staff may record payment only after independent verification. There is **no online card gateway** and no automatic payment collection. Paid orders cannot be cancelled or marked unpaid through this interface; refunds need an external reconciliation process.
- The receipt gives a reference to retain. Cart/receipt state is not a customer-account system. Variants, coupons, subscriptions, invoices, tax/shipping engines, gateways, refund automation and accounting reports are outside this store baseline.
- Apply production traffic controls/CAPTCHA according to your threat model. COD reserves stock before payment and needs operational review/cancellation of abandoned or fraudulent orders. Set appropriate personal-data retention and backup policies.

## Admin localization

- `frontend/src/i18n.ts` supplies reactive English/Dari/Pashto/Arabic localization; preference persists in browser storage.
- `frontend/src/locales/catalog.tsv` contains maintained translations. `aliases.json` maps equivalent UI phrases; generated language JSON files are committed.
- Run `npm run locales:build --prefix frontend` after editing messages, and `npm run locales:check --prefix frontend` to check extracted message coverage. Current catalog: **811 messages**, complete for all three non-English catalogs. A test enforces catalog parity.
- Interface translation is separate from authored site content: website names, product names/descriptions, messages and translated page content remain data. API enum values stay unchanged when translated select labels are used.
- Quasar uses English, Persian/Dari and Arabic packs plus a supplied Pashto pack. Right-to-left document/body layout, right-side navigation, mobile drawer, form labels, table alignment and directional arrows are handled. Currency and inbox dates use the selected locale.
- Laravel selects validation language using `Accept-Language`. Core form validation messages and attributes are included in `backend/lang/{fa,ps,ar}/validation.php`; frontend API errors also have localized status fallbacks.
- These catalogs are implementation translations, not a claim of professional linguistic certification. New features and arbitrary server/audit messages need catalog entries; customer content is never machine-translated.

## API routes

All paths begin `/api`.

Public (published websites; Laravel CSRF applies to POST):
- `POST /public/websites/{site}/contact` — `{name,email,message,language,consent,company}`.
- `POST /public/websites/{site}/newsletter` — `{email,language,consent,company}`.
- `POST /public/websites/{site}/subscription` — `{email,token,action:confirm|unsubscribe}`.
- `GET /public/websites/{site}/store/catalog?page=1`.
- `POST /public/websites/{site}/store/checkout` — `{idempotency_key,name,email,phone,address,consent,items:[{product_id,quantity}]}`.

Authenticated/site-scoped:
- `GET /websites/{site}/inbox?messages_page=1&subscribers_page=1` (`submissions` capability).
- `PUT /websites/{site}/submissions/{submission}` — `{status:new|read|archived}`.
- `DELETE /websites/{site}/engagement/{kind}/{entry}` — kind `submissions|subscribers`.
- `GET|POST /websites/{site}/products`; `PUT /websites/{site}/products/{product}` (`commerce`).
- `GET /websites/{site}/orders?page=1`; `PUT /websites/{site}/orders/{order}` — `{status,payment_status,expected_status,expected_payment_status}` (`commerce`).

## Validation performed

- **21 frontend tests passed**, including three translation tests.
- **25 Node preview integration tests passed**, including contact privacy/consent, one-use newsletter confirmation/unsubscribe, product uniqueness/scoping, authoritative checkout/idempotency, rollback and cancellation stock restoration.
- TypeScript checking + Vite build passed; translation catalog coverage check passed.
- **64 PHP files passed PHP-WASM syntax parsing**. This is not native execution.
- Chromium browser workflow passed: create product in UI, public checkout, cancel and restore stock, submit contact, display inbox message, newsletter confirmation, Dari/Pashto/Arabic admin views and mobile shop. No JavaScript page errors. Separate checks confirmed actual computed RTL body direction, right-side desktop sidebar and right-side mobile drawer.
- Added **six Laravel feature tests** in `BusinessModulesTest.php`. Native Laravel/MySQL/SMTP execution was **not available in this workspace** and remains required before deployment. The GitHub workflow includes MySQL 8 and GD/EXIF; its execution is not claimed here.
