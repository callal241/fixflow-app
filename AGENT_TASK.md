# AGENT_TASK.md — FixFlow POS (work repair POS system)

Last updated: 2026-09-15 (after "product sales wired into the repair flow" step)

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

## Current assignment (PRODUCT SALES → REPAIR FLOW) — DONE
Goal: "wire product sales into the repair flow / ticket workflow" so a ticket can carry
sold products alongside its repair tasks.

### Completed + verified in Docker (branch `docker-preview`)
1. **Schema** — migration `2026_09_15_000001_add_product_sales_to_orders.php` adds
   `product_id` (nullable FK) + `price` to `orders`, so an order can be a catalog-sale
   line item. `Order` fillable extended; `product()` relation added.
2. **Stock handling** — `OrderObserver` (created/updated/deleted) consumes product stock on
   sale, restores it on quantity change / cancel / delete, and clamps at zero so stock never
   goes negative. Delete of an already-cancelled order does NOT double-restore.
3. **Ticket UI + API** — `TicketController` (index/show/storeOrder/destroyOrder) + routes
   `GET tickets`, `GET tickets/{ticket}`, `POST tickets/{ticket}/orders`,
   `DELETE tickets/{ticket}/orders/{order}`. Pages `Tickets/Index.vue` + `Tickets/Show.vue`
   (list products, add a product line to a ticket, remove it). "Tickets" added to AppSidebar.
4. **Invoice wiring** — a product line flows into `Invoice.order_total` (billable orders) →
   `subtotal`, via the existing `fillOrderTotal()`; no parallel sales table.
5. **Second tenant made real** — `SecondBusinessSeeder` gives the second demo business a
   manager login (`manager@secondbird.test` / password) + scoped customers/devices/tickets
   and a small catalog, so multi-tenancy is demonstrable in the app, not just in tests.
   Wired into `DatabaseSeeder` after `CatalogSeeder`.

### Bugs found + fixed during verification
- `Order` `$fillable` was missing `ticket_id`/`business_id` → `MassAssignmentException` on
  the first HTTP sale. Fixed by creating through `$ticket->orders()->create()` (relation sets
  `ticket_id`; `business_id` is stamped by the `BelongsToBusiness` trait).
- `OrderObserver::updated()` cast the original `status` with `(string)`, but Laravel hands it
  an **enum object** → fatal during `OrderSeeder` (which sets status to Cancelled/Shipped).
  Fixed with an `asStatus()` normalizer (handles enum or string). This was a real runtime bug,
  not just a seed issue.

## Verified (proof, not claims) — run inside container `fixflow-app`
- `php -l` passes on all changed PHP files.
- Fresh seed: 108 customers/devices (biz#1), 68 products, 11 categories, **0 NULL business_id**.
- HTTP login admin@demo.com/password → /dashboard **200**; payload: business="FixFlow Demo Shop",
  stats open_tickets=118 customers=108 low_stock_products; 6 recent tickets, 6 low-stock;
  **zero** "Second Bird" (second business) data leaked into the demo dashboard.
- `POST /products` (unique SKU) → 302; row created with `business_id=1` (auto-stamp confirmed).
- Anonymous `GET /dashboard` → 302 /login (isolation holds).

### Product-sales verification (fresh seed, all 16 HTTP/model checks green)
- Fresh seed completes cleanly (incl. `OrderSeeder` status changes + new `SecondBusinessSeeder`).
- Sale: `POST tickets/{t}/orders` (product, qty 2) → 302; product stock 20→18; invoice
  `order_total` = base + (price×qty) and equals the ticket's billable order sum.
- Oversell (qty > stock) → validation error, stock unchanged.
- Cancel (status→cancelled) → stock restored; delete of a cancelled order does NOT
  double-restore (stock unchanged on delete).
- `DELETE tickets/{t}/orders/{o}` (non-cancelled) → 302, stock restored, invoice stable.
- Isolation: biz1 admin → biz2 ticket **403**; biz2 manager → biz1 ticket **403**;
  biz2 sees only its own ticket (200). Second tenant login works.

### Native select dropdown theming (fix verified)
- **Bug:** `<option>` text in the product/category dropdowns was white-on-white in dark
  mode. Root cause: theme is shadcn light/dark (default `system`) but `color-scheme` was
  never set, so the browser painted the native dropdown *list* in the system light scheme
  while the option text inherited the dark `--foreground` (white).
- **Fix:** `resources/css/app.css` now sets `color-scheme: light` on `:root` and
  `color-scheme: dark` on `.dark`, so native controls (select lists, scrollbars) paint to
  match the active theme. Fixes every `<select>` app-wide, both themes.
- **Verified:** rebuilt image; compiled asset `app-CCuyG9zd.css` contains
  `color-scheme:light` + `color-scheme:dark`, and the running login page serves that hash.

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
- Product **edit/update** page (only create exists).
- Ticket **status/notes** workflow (tickets currently list + show; no status transitions yet).
- A **cancel** action for product orders in the UI (stock-restore on cancel is implemented in
  the observer, but there is no HTTP route for it yet — only create/delete are exposed).
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
