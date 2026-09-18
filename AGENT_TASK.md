# AGENT_TASK â€” Finish FixFlow Repair (full spec, 2026-08-17)

## Assignment (authoritative)
Take FixFlow from its current state to a production-ready repair-shop POS + operations
platform per the 57-section spec (customer intake â†’ device ID â†’ ticket â†’ condition docs â†’
repair selection â†’ estimate â†’ approval (pricing snapshot) â†’ part reserve/order â†’ repair â†’
testing â†’ invoice â†’ payment â†’ receipt â†’ close; then suppliers, search, portal, automations,
reporting, Device Bridge, RBAC/audit, multi-location, API/webhooks).
EXISTING app: audit first, preserve working functionality + visual identity, incremental
improvement. No dead buttons, no fake data, no frontend-only features, server-side
validation + permissions. Tenant isolation is critical.

## Prior completed state (verified, branch `docker-preview`)
- Laravel 12 / Inertia / Vue3 / TS / Tailwind(shadcn-style) / SQLite / Docker, PHP 8.4
- 8 business-scoped core models + `BelongsToBusiness` trait (business_id nullable,
  forBusiness() scope, auto-stamp). Business = tenant unit.
- Catalog: Category + Product (active/lowStock scopes). Dashboard, Products (index/create),
  Tickets (index/show), product-sales lines on orders (OrderObserver stock consume/restore),
  invoices aggregate billable orders/tasks.
- Demo seed: biz "FixFlow Demo Shop" (admin@demo.com / password) + "Second Bird"
  (manager@secondbird.test / password) proving tenant isolation. users=19, businesses=2,
  products=86, tickets=121, customers=111, invoices=118, transactions=61.
- Test env fixed & green: 329 passed / 0 failed / 15 design-skips (1250 assertions).
  DO NOT bake ENV back into Dockerfile (reintroduces live-DB shadowing).
- reka-ui Select components exist (dark mode); use them for dropdowns, not native select.
- lucide-vue-next ^0.468.0. No `Table`/`Textarea` UI components (plain markup).
- UI: AppLayout + AppSidebar (Dashboard, Tickets, Products, Settings, Auth).

## Environment / workflow
- Repo callal241/fixflow-app, local `C:\Users\User\OneDrive\Documents\work repair pos system`
  on `docker-preview` (pushed). Work in this dir; push on request.
- Container `fixflow-app`: app /app, DB volume `workrepairpossystem_fixflow-db`:/data,
  HTTP http://127.0.0.1:8790 (port 80 in container).
- Tests: `docker exec fixflow-app sh -c "cd /app && php artisan test --compact"`
- TEST GOTCHA: Inertia JSON coerces whole floats to ints (100.0 -> 100), so
  assert float props with a tolerance closure, never === (bit 1f + 1h again).
- Rebuild: `docker compose build` (Start-Job â†’ C:\fixflow-src\buildN.log; poll) â†’
  `docker rm -f fixflow-app` â†’ `docker compose up -d` â†’ verify. Compose warnings to stderr
  are harmless (NativeCommandError).
- PowerShell: no heredocs (file_editor + docker cp), no &&, chain with ;, timeout<=120s,
  empty output = finished. UNIX grep/find â†’ `wsl -e bash -lc '...'` on /mnt/c/... paths.
- HTTP testing: curl INSIDE container (127.0.0.1:80). Login CSRF dance:
  send captured cookies back RAW (NOT http_build_query - URL-encoding breaks
  EncryptCookies -> 419); X-XSRF-TOKEN header = rawurldecode of the cookie.
  See smoke.php + "Current step" for the full proven procedure.
- Long commands: Start-Job + log file + poll. Never block > ~60s.

## Implementation map â€” FILL DURING AUDIT (Milestone 0)
Statuses: WORKING / PARTIAL / BROKEN / PLACEHOLDER / MISSING
Sections 1-57 of spec. Milestone 1 = full repair lifecycle with real persistent data.

## Checklist
- [x] 0-Audit: backend (controllers/models/migrations/routes/middleware)
- [x] 0-Audit: frontend (pages/components/stores/routes/sidebar)
- [x] 0-Audit: DB schema + enums + seeders
- [x] 0-Map: classify all spec sections (recorded in "Audit findings" below)
- [x] Phase A: schema â€” ticket_number (FF-xxxxx auto + backfill), device fields
      (model_number/imei/color/storage/carrier), ticket internal_notes + intake_type.
      Verified: fresh DB chain + backfill (121 tickets numbered), live DB reseeded,
      suite green (330 passed / 0 failed / 15 skips).
- [x] 1a: Customers (create/edit/list/detail, business accounts, multiple devices)
      DONE (commit 7283556): CustomerController + full Customers/ Vue pages.
- [x] 1b: Devices (manufacturer/model/serial/IMEI/etc., device fields, catalog)
      DONE (commit 7283556): DeviceController + full Devices/ Vue pages.
- [x] 1c: Ticket intake (number, fields, notes, authorization, status)
      DONE (commits 822e498 + 7283556): TicketCreate page + FF-xxxxx number.
- [x] 1d: Ticket workflow (status, tasks/repairs, parts, orders)
      DONE (commit 7283556): Tickets/Show (807 lines: tasks, status, orders,
      payments, invoice, checklist, parts).
- [x] 1e: Estimates/quotes (options, approve, pricing snapshot)
      DONE (2026-09-16, commit f51a42b): invoice IS the estimate (Draft);
      invoices.approved_at + Invoice::approve() re-syncs totals+status in a
      txn (approved+unpaid => Sent); POST tickets/{ticket}/invoice/approve
      (-> InvoiceController::update, whitelisted name) w/ tenant 403 +
      draft-only + billable-work guards; clears billable tasks' approval gate;
      Tickets/Show.vue "Approve estimate" action + Approved badge; 7 tests.
      (options / multiple quote revisions = future enhancement)
- [x] 1f: Inventory (stock, reserved/available, reorder, receiving)
      DONE (2026-08-17, commit 6e21403): product edit + receiving + delete.
      ProductController: edit (render) / update (accepts optional absolute
      "stock" so a restock is a standard update; clamped >=0) / destroy.
      routes: products.edit GET, products.update PUT, products.destroy DELETE.
      Products/Edit.vue (new). Products/Index.vue: per-row Edit/Receive/Remove;
      Receive = inline row w/ live "New on-hand" preview. ProductTest: 10 tests.
      NOTE: first attempt used a dedicated adjustStock() action, but
      FoundationTest runs Pest's built-in laravel preset whose controller-method
      whitelist is fixed (index/show/create/store/edit/update/destroy/...) and
      can't be extended w/o editing vendor; only adjustStock violated it, so
      receiving is modelled as a standard update.
      (reserved/available columns + reorder thresholds = later, with 4/POs.)
- [x] 1g: POS/checkout (payments, deposits, refunds, receipts)
      DONE (commit 7283556): vendor-agnostic PaymentProvider layer (registry,
      result, counter/terminal default) + per-business provider in Settings
      -> Payments + balance quick-charge on ticket checkout; PaymentsTest.
      (Refunds/receipts polish = 1h/1j follow-up.)
- [x] 1h: Invoices (from ticket, pay, status)
      DONE (2026-08-17, commit 0f8e074): Invoices Index (searchable list) +
      Show (printable invoice document: line items, adjustments, payments,
      running totals, Print/PDF). invoices.index/show routes, sidebar link,
      print:hidden on AppSidebar + AppSidebarHeader. 4 InvoicesTest cases.
      Full suite green (383 passed / 15 skip). Milestone 1 now fully closed.
- [x] 1i: Testing/checklists (pre/post repair)
      DONE (2026-09-16): pre/post-repair QC checklist on the ticket.
      New ChecklistItem model (business-scoped, non-billable, never affects
      invoice totals) + ChecklistPhase (pre_repair/post_repair) +
      ChecklistStatus (pending/passed/failed) + checklist_items migration
      (inline business_id, post-dates scoping). ChecklistItemController
      (store/update/destroy, tenant 403 + relation-scoped 404) behind 3
      routes; Ticket::checklistItems() relation + TicketController::show()
      props (checklist_items ordered pre-then-post, eager checkedBy, +
      checklist_phases/statuses option values). Factory + checked() state.
      Tickets/Show.vue "Testing & QC" card: pre/post groups, status Select,
      add form, progress (passed/total + failed count), checker+time display.
      9 feature tests (ChecklistTest): CRUD, status transitions + reset,
      tenant 403, wrong-ticket 404, validation, page payload. Suite 367 pass /
      0 fail / 15 skip (1460 assertions); Vite build green. Live in-process
      E2E 17/17 (kernel + auth + CSRF + tenant).
      NOTE: HasType hardcodes column "type" so ChecklistItem casts phase
      manually instead of using the trait.
- [x] 1j: End-to-end verification with real data + tests
      DONE (2026-08-17, commit 77bc813): kernel E2E probe through the real
      DB (intake -> task -> invoice -> approve -> payment -> show) = 20/20.
      Found + fixed a real 500 on the ticket page after any payment
      (Collection->latest()); added TicketShowTest; killed a pre-existing
      flaky intake test (assertSee on a model containing a double quote).
      Suite deterministic: 369 pass / 0 fail / 15 skip, 10/10 green runs.
- [x] 2: Global search + command palette (Ctrl/Cmd+K)
      DONE (commit 7283556): SearchController + /search + Ctrl/Cmd+K palette
      across tickets/customers/devices/products; SearchTest.
- [x] 3: Supplier provider architecture + MobileSentrix/PhoneLCD/iFixit
      DONE (2026-08-17): vendor-agnostic SupplierProvider layer, mirrors the
      Payments architecture. app/Suppliers/: SupplierProvider contract,
      PartOffer + PartSearchResult value objects (success/failure semantics,
      toPayload), UnknownSupplierException, SupplierProviderRegistry
      (resolve/for-business w/ configured fallback to default, options() with
      label+description+configured+hint). Providers: HttpSupplierProvider
      (shared curl/status/json/parse), IFixitProvider (REAL: public
      /suggest/{query}, no key, maps guide/part rows, price+stock stay null
      because the API provides none -- never invented), MobileSentrixProvider
      + PhoneLcdProvider (B2B, key-driven; honest isConfigured()=false +
      setup hint until MOBILESENTRIX_*/PHONELCD_* env present; fail gracefully,
      never crash), ManualSupplierProvider (guaranteed fallback). config/
      suppliers.php gateways; AppServiceProvider binds registry singleton;
      businesses.supplier_provider_id migration + Business fillable.
      SupplierController (index page + update persist w/ unconfigured->null
      normalisation) + SupplierSearchController::index (JSON parts search;
      separate controller because Pest's laravel arch preset whitelists
      controller method names and 'search' is not one -- same constraint that
      shaped 1f receiving). Routes: GET suppliers, PUT suppliers, GET
      suppliers/search. Frontend: Suppliers/Index.vue (provider picker + live
      search, mirrors Business.vue + SearchCommand.vue) + sidebar link.
      Tests: SuppliersTest 23 cases -- registry resolve/fallback/options,
      iFixit REAL HTTP path against a recorded fixture served by php -S (no
      live net in tests; exercises curl->status->json->parse end to end),
      non-2xx + non-JSON upstream surface as failure, B2B unconfigured/configured
      + parse, VOs, page render, search endpoint (tenant-scoped, auth,
      validation), update persist+normalisation. NOTE: the search endpoint
      reads the acting tenant's business per request; a PUT that reuses one
      acting User instance across many requests in a test caches user->business,
      so the null-clear assertion uses a fresh user (real requests build a
      fresh user). Full suite green 408 pass / 0 fail / 15 skip (1742 asserts).
      Live HTTP smoke (smoke.php section 5, 6 checks) all PASS incl. real
      iFixit search returning 4 real offers over the container's live net.
      MobileSentrix public endpoint is behind Cloudflare (403 to bots) and
      PhoneLCD has no usable public API -- both are wired as key-driven B2B
      gateways that the user configures with real partner credentials later.
- [ ] 4: Parts from ticket (Find Parts), purchase orders, receiving
- [ ] 5: Communications + customer portal
- [ ] 6: Reporting + dashboards (technician/store)
- [ ] 7: Automations, notifications
- [ ] 8: RBAC + audit log + passcode sensitivity
- [ ] 9: Multi-location, appointments, mail-in
- [ ] 10: Device Bridge
- [ ] 11: API/webhooks, accounting adapters
- [x] 12: Full end-to-end QA
      DONE (2026-08-17, commit 0b3acc1): smoke.php drives REAL HTTP against the
      container (127.0.0.1:80): login CSRF dance -> all list pages on seeded
      data -> every detail page -> auth gating (logged-out 302 to /login) ->
      tenant isolation (Second Bird 403 on a FixFlow ticket, sees own 3).
      17/17 pass. Found + fixed 3 real-data bugs the in-process suite missed:
      (1) InvoiceController show() adjustments select lacked invoice_id (500
      on any invoice with an adjustment); (2) Device $appends=device_progress
      had no matching accessor (ticketProgress) -> device detail 500; (3)
      DeviceController malformed eager-load 'tickets.device.customer.id'.
      Added DeviceTest (2) + adjustment to InvoicesTest fixture. Suite:
      385 pass / 0 fail / 15 skip (1631 assertions).

## Current step
Milestone 3 (supplier provider architecture) DONE (2026-08-17) - see checklist
entry 3 above for the full write-up. Suite green 408/15, live smoke 23/23.
NEXT = Milestone 2 item 4: Find Parts from ticket + purchase orders +
receiving (incl. reserved/available stock). The SupplierProvider layer from
milestone 3 is the hook Find Parts plugs into (per-tenant active supplier).
Remaining Milestone-2 order after 4: 5 (comms + customer portal), 6
(reporting/dashboards), 7 (automations/notifications), 8 (RBAC/audit),
then 9-11. 12 (full E2E QA) is DONE - see checklist entry above.

HTTP E2E PROCEDURE (proven 2026-08-17, see smoke.php - reuse it):
run `docker cp smoke.php fixflow-app:/tmp/smoke.php && docker exec
fixflow-app php /tmp/smoke.php`. The login CSRF dance that WORKS:
  GET /login, capture BOTH cookies; send them back RAW in the Cookie header
  (NEVER http_build_query - it URL-encodes values and EncryptCookies can't
  decrypt the cookie -> session lost -> 419); X-XSRF-TOKEN header =
  rawurldecode() of the XSRF-TOKEN cookie value (axios does exactly this).
  POST /login -> 302 /dashboard. The XSRF-TOKEN cookie rotates, re-read it
  after each response.
  NOTE: /app is baked into the image (only /data is a volume) - to test a
  source change live: docker cp <file> fixflow-app:/app/... (no opcache
  reload needed); to persist: rebuild the image (docker compose build).

STATE (was stale, corrected 2026-08-17): this file was badly stale. The repo
is far past the
"MISSING: Customers/Devices/intake" audit further below. Verified today against
the live container + git history:
- 1a/1b Customers+Devices CRUD: CustomerController/DeviceController + full
  Customers/ & Devices/ Vue pages (Index/Create/Edit/Show) - commit 7283556.
- 1c/1d Ticket intake + workflow UI: Tickets/Create + Tickets/Show (807 lines:
  tasks, status, orders, payments, invoice, checklist, parts).
- 2 Global search (Ctrl/Cmd+K) + 1g payments: commit 7283556.
- 1e estimate/approval + 1i checklist: DONE, committed (f51a42b, ed87d38).
- Live check today: container Up 22h; login admin@demo.com/password -> 302
  dashboard; /customers /devices /tickets /customers/create /tickets/create
  all 200 with real data-page. Full suite in container: 367 passed / 0 failed /
  15 skipped (1460 assertions).

REMAINING Milestone-1 gaps: NONE (1f, 1h, 1j all done). 1h shipped 0f8e074.
1j CORE DONE (2026-08-17, commit 77bc813): drove the full repair lifecycle
end-to-end against the live DB via a kernel probe (intake w/ new customer+
device+ticket -> billable task -> generate invoice -> approve -> cash payment
-> show page): 20/20 checks passed. It surfaced a real break:
- TicketController::show() 500'd for ANY ticket with a recorded
  payment/refund because the invoice payload called ->latest() on the
  loaded transactions Collection (query method on a Collection). Fixed to a
  key-based sortByDesc(created_at). Added TicketShowTest (2 regression
  tests). Also killed a pre-existing flaky test: RepairIntakeTest
  "ticket detail renders..." used assertSee($device->model), but ~1/3 of
  DeviceFactory models contain a double quote ('iMac 27"') stored in the
  Inertia data-page attr as \&quot; (assertSee can't match) -> now asserts
  the Inertia props directly. Full suite now deterministic (10/10 green).
NEXT = 12 full E2E QA. Milestone 1 (repair lifecycle with real data) is fully
closed: 1a-1j all done. 1h shipped 0f8e074 (Invoices list + printable detail).
1f DONE (2026-08-17, commit 6e21403): product edit + receiving + delete.
ProductController: edit (render) / update (accepts optional absolute "stock"
so a restock is a standard update; clamped >=0) / destroy. routes:
products.edit GET, products.update PUT, products.destroy DELETE.
Products/Edit.vue (new). Products/Index.vue: per-row Edit/Receive/Remove;
Receive = inline row w/ live "New on-hand" preview. ProductTest: 10 tests.
NOTE: first attempt used a dedicated adjustStock() action, but FoundationTest
runs Pest's built-in laravel preset whose controller-method whitelist is fixed
(index/show/create/store/edit/update/destroy/...) and can't be extended w/o
editing vendor; only adjustStock violated it, so receiving is modelled as a
standard update. Full suite 379 pass / 0 fail / 15 skip. Docker image rebuilt
+ recreated; live smoke test: /products + /products/{id}/edit both 200.
## Audit findings (2026-08-17, verified against repo + live DB)

### BACKEND â€” WORKING (solid foundation, 329 tests pin the contract)
- 12 models, all business-scoped via BelongsToBusiness (business_id, forBusiness scope,
  auto-stamp). Enums w/ HasNext + HasProgress (pending/complete cases): TicketStatus
  (new/in_progress/on_hold/resolved/closed), TaskType (10 types), TaskStatus, OrderStatus,
  DeviceStatus (received/on_hold/under_repair/ready/delivered), DeviceType, InvoiceStatus
  (draft/issued/sent/paid/refunded/cancelled), Priority (low..urgent), UserRole
  (admin/manager/technician), TransactionMethod/Type, AdjustmentType/Reason.
- Observer engine (WORKING, tested): TaskObserver + OrderObserver â†’ invoice totals;
  InvoiceObserver â†’ % adjustments; TransactionObserver â†’ paid/refunded + auto status;
  Ticket/Device/Adjustment â†’ rollup counts. Invoice computed props: subtotal, net_amount,
  balance, getComputedStatus.
- Controllers/routes EXIST: Dashboard, Product (index/create/store), Ticket (index/show),
  Order (store/destroy), full Auth suite, Settings (profile/password).
- Factories + seeders for ALL models (states: forCustomer/forDevice/forTicket/billable/â€¦).
- Workflow feature tests define E2E contract: customerâ†’deviceâ†’ticketâ†’invoiceâ†’tasksâ†’
  ordersâ†’adjustmentâ†’paymentâ†’paid.

### FRONTEND â€” WORKING (good kit, thin app layer)
- Full shadcn/reka-ui kit: Button, Card, Input, Label, Select, Checkbox, Dialog,
  DropdownMenu, Sheet, Tooltip, Avatar, Breadcrumb, Sidebar, Separator, Skeleton,
  Collapsible, NavigationMenu. AppLayout + AppSidebar + breadcrumbs + Heading.
- Inertia 2 + Ziggy (route() in Vue) + useForm. Tailwind 4, dark mode. lucide icons.
- Pages EXIST: Dashboard, Welcome, 7 auth, Products/Index+Create, Tickets/Index+Show,
  Settings x3. Sidebar: Dashboard, Products, Tickets.

### THE GAP â€” application layer (Milestone 1 blockers)
- MISSING: Customers CRUD (no controller/routes/pages).
- MISSING: Devices CRUD.
- MISSING: Ticket create (intake) + update (status/assignee/priority/due/notes).
- MISSING: Tasks UI (model+enum+observer all exist; zero UI/routes).
- MISSING: Invoice UI (create-from-ticket, adjustments, payments, refunds, receipt).
- MISSING: free-form parts (orders without product_id) in UI (product sale only).
- MISSING: ticket number (spec Â§1) â€” tickets have only autoincrement id.
- MISSING: device detail fields (imei/color/storage/carrier â€” spec Â§1/Â§2).
- MISSING: internal vs customer-visible notes.
- MISSING (later milestones): global search, RBAC enforcement, audit log, photos,
  labels/QR, estimates-as-portal, suppliers, comms, automations, reports, device bridge,
  multi-location, API/webhooks.

### DECISIONS (documented per spec Â§55)
- Keep stack (Laravel/Inertia/Vue/SQLite/Docker) â€” spec Â§47. Build application layer on
  existing models/observers; do NOT rewrite engine.
- Ticket number: `FF-` + 5-digit zero-padded global id (set on creating if empty) â€”
  stable, searchable, tenant-unique.
- Estimate = Invoice(Draft) created from ticket; approval = invoice sent + task approvals.
  (Matches existing architecture; InvoiceStatus already models the lifecycle.)
- v1 device fields: imei, color, storage, carrier (rest later). Photos/QR/suppliers
  post-milestone.
- Roles: keep enum; add server-side role guards on sensitive routes (admin settings)
  when building; full RBAC matrix post-milestone.

