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

Phase 2/3 (cart→payment upload, Billplz, WhatsApp AI, customer login,
scent refill subscription) are scaffolded via the data model and admin
modules and can be layered on without schema changes.

## Security

PDO prepared statements · CSRF tokens on all POST forms · output escaped
with `htmlspecialchars` · validated image uploads (jpg/png/webp only) ·
script execution disabled in `/uploads` · sensitive dirs HTTP-blocked ·
supplier cost is admin-only and never rendered publicly.
