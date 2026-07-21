# Laundré Portal — Laravel 11 backend

Full-system foundation for the Laundré operations & franchise portal: MySQL database,
session auth, four roles with **server-enforced** per-store scoping, a REST API, and
seeders preloaded with your 3 laundromats and June 2026 sales.

## What's included
- **Schema** (migrations) for every entity: locations, users(+roles), sales, documents,
  guides, tickets(+messages), cleaning logs, maintenance logs, suppliers, cost projects,
  site scores, franchises (checklists), bookkeeping, settings.
- **Auth**: session login (`/login`), roles `admin | franchisee | cleaner | maintenance`,
  `role:` middleware + in-controller scoping so a franchisee/cleaner/maintenance user can
  only ever read/write their own store's rows.
- **API** (`routes/api.php`, session-authenticated via Sanctum): locations, users, sales
  (+ `POST /api/sales/import`), documents, guides, tickets, cleaning, maintenance,
  suppliers, cost projects, site scores, franchises, bookkeeping, settings.
- **Seeders**: 3 locations, an admin user, and the real June 2026 daily sales
  (Approved only — grand total $68,203.29).
- **Transitional UI**: your existing tool pages are served, auth-gated, under `/legacy/*`
  while each is wired to the API (phase 2). The Blade `portal` view lists them by role.

## Requirements
PHP 8.2+, Composer, MySQL 5.7+/MariaDB. On GoDaddy cPanel you need **SSH** (or cPanel's
Composer) to run `composer install`.

## 1) Push to GitHub (three commands)
From inside this folder:
```bash
./push.sh        # runs: git init && add && commit && push to laundreaus/laundre-portal
```
…or manually:
```bash
git init && git add . && git commit -m "Laravel 11 foundation"
git branch -M main
git remote add origin https://github.com/laundreaus/laundre-portal.git && git push -u origin main
```

## 2) Run locally (optional)
```bash
composer install
cp .env.example .env && php artisan key:generate
# edit .env DB_* for your MySQL
php artisan migrate --seed
php artisan serve
```
Login: **admin@laundre.com.au / laundre2026** (change immediately).

## 3) Deploy on cPanel (staging first, then live)
1. **Create the subdomain**: cPanel → Domains → `staging.laundre.com.au`, and set its
   **Document Root** to `…/laundre-portal/public` (see DEPLOYPATH below).
2. **Create a MySQL database + user** (cPanel → MySQL® Databases), grant ALL, note the
   `dbname / dbuser / dbpass`.
3. **Connect Git**: cPanel → Git™ Version Control → Create → clone
   `https://github.com/laundreaus/laundre-portal.git`.
4. **Edit `.cpanel.yml`**: set `DEPLOYPATH` to your real home path,
   e.g. `/home/laundreaus/laundre-portal`. Commit & push that change.
5. On the server (SSH once): create `.env` from `.env.example`, set `APP_URL`,
   `APP_ENV=production`, DB_*; run `php artisan key:generate`.
6. cPanel → Git → **Manage → Deploy HEAD Commit** runs `.cpanel.yml`
   (composer install, `migrate --force`, storage:link, config/route cache).
7. Repeat for **live** (`dashboard.laundre.com.au`) with a separate database and DEPLOYPATH,
   after you've tested on staging.

> Point each domain's document root at the app's `public/` directory — never expose the
> project root. Keep `.env` out of git (it already is).

## Roles & data isolation
`admin` sees everything. `franchisee`/`cleaner`/`maintenance` are tied to a `location_id`;
every scoped endpoint filters by it in the controller, so store data is isolated on the
server — not just hidden in the UI.

## Phase 2 (next)
Wire each `/legacy` tool's JavaScript to call the API endpoints instead of `localStorage`
(auth via the session cookie + CSRF). The endpoints already exist and are scoped.

---

## Staging server — provisioned in cPanel (ts3idy9njqfd)
Already created for you:
- **Subdomain**: `staging.laundre.com.au` → Document Root `/home/ts3idy9njqfd/laundre-portal/public`
- **Database**: `laundre_staging`

You still need to (I can't create credentials):
1. **MySQL® Databases → MySQL Users → Add New User**: create a user (e.g. `laundre_stg`),
   set a strong password, then under **Add User To Database** add that user to
   `laundre_staging` and grant **ALL PRIVILEGES**.
2. Put those into the server `.env`:
   ```
   APP_URL=https://staging.laundre.com.au
   DB_DATABASE=laundre_staging
   DB_USERNAME=laundre_stg      # your user
   DB_PASSWORD=********         # your password
   ```
3. **Git™ Version Control** → clone `https://github.com/laundreaus/laundre-portal.git`,
   then **Manage → Deploy HEAD Commit** (runs `.cpanel.yml`: composer install, migrate --force,
   storage:link, caches). `.cpanel.yml` DEPLOYPATH is already set to
   `/home/ts3idy9njqfd/laundre-portal`.
4. DNS: ensure `staging.laundre.com.au` has an A record → **192.169.148.148**
   (this server's shared IP) at wherever laundre.com.au DNS is hosted.

Live (`dashboard.laundre.com.au`) already exists with docroot `/public_html/dashboard.laundre.com.au`
— repeat the deploy against a separate DB once staging is verified, and repoint its docroot to the app's `/public`.
