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

Remaining Phase 3 (Billplz/FPX gateway, live WhatsApp AI integration,
scent refill subscription) layers on without schema-breaking changes.

## Security

PDO prepared statements · CSRF tokens on all POST forms · output escaped
with `htmlspecialchars` · validated image uploads (jpg/png/webp only) ·
script execution disabled in `/uploads` · sensitive dirs HTTP-blocked ·
supplier cost is admin-only and never rendered publicly.
