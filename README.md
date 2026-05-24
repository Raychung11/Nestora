# NESTORA.my — Emotional Comfort Living (MVP)

**A Home That Takes Care of You.** Native PHP + MySQL e-commerce MVP for
furniture and essential oils, built for Hostinger shared hosting (no Laravel,
no Node.js).

## Tech

- Native PHP 8+ (PDO, prepared statements, `password_hash`)
- MySQL / utf8mb4
- Plain HTML / CSS / JS — premium, warm, responsive
- No build step, no framework

## Project structure

```
/                public pages (index, products, product, cart, checkout, …)
/config          db_config.php (edit DB credentials here)
/inc             functions, header, footer, auth, admin_layout
/admin           admin console (login, dashboard, products, orders, …)
/assets          css / js / images
/uploads         products / banners / brand  (writable; scripts blocked)
/database        schema.sql + seed.sql
install.php      one-time installer (DELETE after running)
```

## Setup (local or Hostinger)

1. Create a MySQL database, then edit **`config/db_config.php`** with the
   database name, user and password (or set `NESTORA_DB_*` env vars).
2. Run the installer once:
   - CLI: `php install.php`
   - Browser: `https://yourdomain/install.php?key=INSTALL`
3. **Delete `install.php`** from the server.
4. Visit `/admin/login.php` and sign in:
   - Email: `admin@nestora.my`
   - Password: `NestoraAdmin123`
5. Change the admin password immediately in **Admin → Settings**.
6. Make `uploads/` writable (e.g. `chmod -R 775 uploads`).

> On Hostinger: upload the repository contents into `public_html/`.
> `BASE_URL` can stay empty when the site is at the domain root.

## Phase 1 (delivered)

Database schema · admin login · product management (with images) ·
premium homepage · product listing & detail (furniture + essential oil) ·
Comfort Quiz with lead capture & recommendations · order inquiry / checkout ·
admin dashboard (orders, leads, installment, top products, repeat scent
customers) · categories · customers · suppliers · installment requests ·
banners · testimonials · site/content settings · admin users.

## Phase 2 (delivered)

Manual payment upload (`payment.php`) with bank details + image/PDF proof,
admin verification (`admin/payments.php` → marks order paid/failed) ·
standalone installment application (`installment_apply.php`) feeding the
existing admin installment workflow · Nestora AI Comfort Advisor
qualification flow (`comfort_advisor.php`) that captures a WhatsApp lead
and hands off to WhatsApp with a prefilled summary, managed in
`admin/whatsapp_leads.php` · supplier management & cart/checkout shipped
in Phase 1. Dashboard now tracks payments awaiting verification.

Run `database/phase2.sql` (or re-run `install.php`) to add the
`payment_proofs` table and bank settings to an existing Phase 1 database.

Customer accounts (a Phase 3 item) have been brought forward: register /
login / logout, a "My Account" page with order history, and checkout that
prefills and links orders to the signed-in account (`customers.password_hash`
was already in the schema, so no migration needed).

Transactional email: a dependency-free SMTP mailer (`inc/mailer.php`)
sends admin alerts and customer confirmations for contact, orders,
payment proofs, quiz/advisor leads and installment requests. Configure
SMTP in Admin -> Settings (Hostinger: smtp.hostinger.com, SSL 465,
username = full mailbox email). The password is stored in site_settings
or via the `NESTORA_SMTP_PASS` env var. Sending is best-effort and never
blocks a customer action.

Bundle packages & documents (Phase 3): a mix-and-match bundle builder
(`admin/bundles.php` + `admin/bundle_form.php`) — pick products, set one
bundle price; the "worth" (sum of selling prices) and bundle cost are
auto-computed for the customer saving and your margin. A bundle is a
`product_type='bundle'` product, so active bundles appear automatically
in the Comfort Bundles catalog (`/products.php?type=bundle`) and buy
through the normal cart/checkout. Products gained `base_price` (RRP,
struck-through when higher) and `cost_price` (internal costing; margin
shown in the product form). Invoices are issued automatically at
checkout and receipts when payment is verified; both are branded
print-to-PDF pages (`/document.php`) gated by an admin/owning-customer
session or an unguessable signed token, and auto-emailed via the SMTP
mailer. Run `database/phase3.sql` (or re-run `install.php`) to add the
pricing columns, `bundle_items` table and invoice/receipt fields to an
existing database.

HitPay online payment (Phase 4): "Pay online now" at checkout (card /
FPX / e-wallet) via the HitPay v1 Payment Request API. The customer is
redirected to HitPay's hosted checkout; a signed server-to-server webhook
(`/hitpay_webhook.php`, HMAC-SHA256 with the account Salt, plus an API
re-confirmation) marks the order paid and auto-issues/e-mails the
receipt. Manual bank transfer stays available alongside it. Configure in
Admin -> Settings -> "HitPay online payment": enable, mode
(sandbox/live), currency, API key and Salt (API key/Salt are write-only
secrets). Set the HitPay webhook URL to `<site>/hitpay_webhook.php`.
Order-success and account pages expose a token-gated retry link
(`/hitpay_pay.php`); customers land on `/payment_return.php` afterwards.
Run `database/phase4.sql` (or re-run `install.php`) to add the
`payment_gateway`/`payment_ref` columns, extend the payment-method enum
and seed the HitPay settings.

Webhook scheme note: this targets the classic HitPay v1 payment-request
webhook (sorted key+value HMAC in the `hmac` field). Validate in Sandbox
before going live; if HMAC check fails, payments still confirm manually
in Admin -> Orders (safe fallback, never auto-paid without confirmation).

Phase 5 enhancements:

- Discount / voucher codes: percentage or fixed, with min-spend, usage
  limit, and date window. Customers apply a code in the cart; it is
  re-validated live, persisted on the order (subtotal/discount/total) and
  reflected in emails, HitPay charge and installment maths. Managed in
  Admin -> Voucher Codes (`inc/vouchers.php`, `admin/vouchers.php`).
- Stock & inventory: opt-in per product (`track_inventory`,
  `stock_quantity`, `low_stock_threshold`). Stock auto-decrements once
  when an order is paid (guarded by `orders.stock_decremented`; a bundle
  reduces its component products), out-of-stock blocks add-to-cart and
  checkout, and the dashboard lists low-stock items (`inc/inventory.php`).
- Order-status notifications: customers are emailed a friendly update
  with a tracking link when an order enters a customer-facing status
  (each status at most once), plus a public `track.php` tracking page by
  order number (`inc/notifications.php`).
- Security hardening: per-IP login throttling for admin & customer
  sign-in (`login_attempts` table, lock after 5 fails for 15 min,
  `inc/security.php`), hardened session cookie (HttpOnly/SameSite/Secure)
  and baseline security headers in the bootstrap.

Run `database/phase5.sql` (or re-run `install.php`) to add the voucher /
inventory / notification columns and the `vouchers` + `login_attempts`
tables to an existing database.

Remaining (live WhatsApp AI integration, scent refill subscription)
layers on without schema-breaking changes.

## Security

PDO prepared statements · CSRF tokens on all POST forms · output escaped
with `htmlspecialchars` · validated image uploads (jpg/png/webp only) ·
script execution disabled in `/uploads` · sensitive dirs HTTP-blocked ·
supplier cost is admin-only and never rendered publicly.
