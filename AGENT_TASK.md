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
- Rebuild: `docker compose build` (Start-Job â†’ C:\fixflow-src\buildN.log; poll) â†’
  `docker rm -f fixflow-app` â†’ `docker compose up -d` â†’ verify. Compose warnings to stderr
  are harmless (NativeCommandError).
- PowerShell: no heredocs (file_editor + docker cp), no &&, chain with ;, timeout<=120s,
  empty output = finished. UNIX grep/find â†’ `wsl -e bash -lc '...'` on /mnt/c/... paths.
- HTTP testing: curl INSIDE container (127.0.0.1:80); XSRF via cookie from /login,
  send as X-XSRF-TOKEN (URL-decoded); re-read cookie after login (rotates).
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
- [ ] 1a: Customers (create/edit/list/detail, business accounts, multiple devices/contacts)
- [ ] 1b: Devices (manufacturer/model/serial/IMEI/etc., device catalog hierarchy)
- [ ] 1c: Ticket intake (number, fields, photos, notes, authorization, status)
- [ ] 1d: Ticket workflow (status transitions, notes/timeline, tasks/repairs, parts)
- [x] 1e: Estimates/quotes (options, approve, pricing snapshot)
      DONE (2026-09-16, commit f51a42b): invoice IS the estimate (Draft);
      invoices.approved_at + Invoice::approve() re-syncs totals+status in a
      txn (approved+unpaid => Sent); POST tickets/{ticket}/invoice/approve
      (-> InvoiceController::update, whitelisted name) w/ tenant 403 +
      draft-only + billable-work guards; clears billable tasks' approval gate;
      Tickets/Show.vue "Approve estimate" action + Approved badge; 7 tests.
      (options / multiple quote revisions = future enhancement)
- [ ] 1f: Inventory (stock, reserved/available, reorder, receiving)
- [ ] 1g: POS/checkout (payments, deposits, refunds, receipts)
- [ ] 1h: Invoices (from ticket, pay, status)
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
- [ ] 1j: End-to-end verification with real data + tests
- [ ] 2: Global search + command palette (Ctrl/Cmd+K)
- [ ] 3: Supplier provider architecture + MobileSentrix/PhoneLCD/iFixit
- [ ] 4: Parts from ticket (Find Parts), purchase orders, receiving
- [ ] 5: Communications + customer portal
- [ ] 6: Reporting + dashboards (technician/store)
- [ ] 7: Automations, notifications
- [ ] 8: RBAC + audit log + passcode sensitivity
- [ ] 9: Multi-location, appointments, mail-in
- [ ] 10: Device Bridge
- [ ] 11: API/webhooks, accounting adapters
- [ ] 12: Full end-to-end QA

## Current step
DONE (2026-09-16): Testing/checklists (1i) - see 1i checklist note above.
Pre/post-repair QC checklist on the ticket (non-billable, tenant-isolated).
Backend + UI + 9 feature tests + 17-point live in-process E2E all green;
full suite 367 pass / 0 fail / 15 skip; Vite build green.
PENDING: docker compose build to bake the new code into the image (code is
currently live via docker cp only), then commit on docker-preview.
NEXT (choose by spec value): 1a Customers CRUD and/or 1b Devices CRUD are the
biggest remaining Milestone-1 gaps (both models+factories+scopes already exist;
need controller/routes/pages). 1f Inventory, 1g POS/checkout, 1h Invoices UI
follow. (1e estimate/approve is already done.)
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

