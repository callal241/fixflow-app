# AGENT_TASK.md — FixFlow POS (work repair POS system)

Last updated: 2026-09-15 (after "core fundamentals" step)

## What this app is
Device-repair POS built from a GitHub base (Laravel 12 + Inertia + Vue 3 + TS + SQLite in Docker).
The business does **both repair and sales** of electronics / odd items, so the app must support
a sellable catalog, not just repairs. Data is **business-scoped** (multi-tenant): every core
row has `business_id`; users without a business see **no data** (secure default).

## Current assignment (CORE FUNDAMENTALS) — DONE
Goal: "build core fundamentals, make everything flexible/easy to add to or change later."

### Completed + verified in Docker (commit 39bf8a8, branch `docker-preview`)
1. **Business scoping** — `BelongsToBusiness` trait (app/Models/Concerns/) on all 8 core models
   (Customer, Device, Ticket, Task, Order, Invoice, Transaction, Adjustment). Trait provides
   `business()` relation + `forBusiness()` scope + auto-stamp on create. User without a
   business → empty result set (no unscoped leak).
2. **Catalog (sales side)** — `Category` (nested via `parent_id`) + `Product` models with
   `active()` / `lowStock()` scopes, stock/reorder tracking, category relation.
3. **Seeders** — `BusinessSeeder` (demo business + a second business to prove isolation),
   `CatalogSeeder` (category tree + ~68 products). ALL demo seeders stamp `business_id`
   by inheriting from the parent row (customer→device→ticket→…→transaction).
4. **Dashboard** — `DashboardController` + `Dashboard.vue`: scoped stats (open tickets,
   ready devices, customers, low-stock products), recent tickets, low-stock list.
5. **Products** — `ProductController` + `Products/Index.vue` + `Products/Create.vue`.
   `store()` auto-stamps `business_id`. "Products" item in AppSidebar.

### Two bugs found + fixed during verification
- `Ticket` model was **missing the `BelongsToBusiness` trait** → `forBusiness()` undefined →
  dashboard 500. (All other 7 core models had it; Ticket slipped through.)
- `User::business()` return type was `Eloquent\BelongsTo` (abstract base) but the call returns
  `Eloquent\Relations\BelongsTo` → TypeError. Fixed to `Relations\BelongsTo`.

## Verified (proof, not claims) — run inside container `fixflow-app`
- `php -l` passes on all changed PHP files.
- Fresh seed: 108 customers/devices (biz#1), 68 products, 11 categories, **0 NULL business_id**.
- HTTP login admin@demo.com/password → /dashboard **200**; payload: business="FixFlow Demo Shop",
  stats open_tickets=118 customers=108 low_stock_products; 6 recent tickets, 6 low-stock;
  **zero** "Second Bird" (second business) data leaked into the demo dashboard.
- `POST /products` (unique SKU) → 302; row created with `business_id=1` (auto-stamp confirmed).
- Anonymous `GET /dashboard` → 302 /login (isolation holds).

## How to build/run (Docker, Windows PowerShell)
- **Mirror** the repo to `C:\fixflow-src` (robocopy, exclude node_modules/.git/.openhands) because
  OneDrive placeholder files break `docker build` context reads. Build from the mirror:
  `docker build -t fixflow-app:latest .` (in C:\fixflow-src).
- Recreate container:
  `docker run -d --name fixflow-app -p 127.0.0.1:8790:80 -v workrepairpossystem_fixflow-db:/data --restart unless-stopped fixflow-app:latest`
- Fresh seed: `docker volume rm workrepairpossystem_fixflow-db` first (else old unscoped rows persist).
- App URL: http://127.0.0.1:8790  ·  login admin@demo.com / password
- Lint a file in the container: `docker run --rm --entrypoint php -v C:\fixflow-src:/app -w /app fixflow-app:latest -l /app/<path>`
- HTTP tests: curl **inside the container** against `127.0.0.1:80` (8790 is the host mapping).
  For POSTs, fetch the XSRF-TOKEN cookie from /login and send it as an `X-XSRF-TOKEN` header
  (URL-decoded). **Re-read the cookie after login** (it rotates).
- PowerShell gotchas that already cost time: no `grep`/`find`/`sed` in the host shell; use
  `wsl -e bash -lc '...'` or a script file for anything UNIX-shaped; multi-line `php -r` and
  nested quotes get mangled by PowerShell — put PHP in a `.php` file and `docker cp` it in;
  `timeout` is in seconds (max 120); empty output = finished, not a hang.

## Pending / next (not started)
- **Wire product sales into the repair flow** (attach products to tickets/orders, stock
  deduction on sale) — the natural next step for "fix AND sell."
- Product **edit/update** page (only create exists).
- Category management UI (categories are only seeded + shown as a form select).
- Inventory receiving/purchase orders (stock is currently set by hand).
- Reports (sales, repairs, revenue per business).
- Push `docker-preview` to GitHub / open PR (NOT done — user has not asked).
- Frontend typecheck (vue-tsc) — no local node_modules; would need to run in a node container.

## Conventions
- Models: `HasFactory` + `BelongsToBusiness` + existing concerns (`HasStatus`, etc.).
- Factories: `fake()` + states.
- Seeders reference the demo business via `BusinessSeeder::DEMO_BUSINESS_NAME`.
- Vue pages: `AppLayout`, `Heading`, `InputError`, `useForm`, `router`, `Head`.
- **No** `Table` or `Textarea` UI components exist — use plain markup/`<textarea>`.
- lucide-vue-next `^0.468.0`; verified icon names: TriangleAlert, AlertTriangle, Wrench,
  Package, Users, Plus.
